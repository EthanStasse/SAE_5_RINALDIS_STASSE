#!/usr/bin/env python3
"""Passerelle capteurs de serre : Arduino (USB série) -> API Laravel (HTTPS).

Usage :
    python3 passerelle.py --config /etc/passerelle-serre/config.toml [-v]

Architecture :
    - un thread LiaisonBoitier par Arduino : lit les mesures, les horodate
      en UTC et les écrit dans le tampon SQLite ;
    - le thread principal (envoyeur) vide le tampon vers l'API.
"""

import argparse
import logging
import signal
import sys
import threading
import time
import tomllib
import uuid
from datetime import datetime, timezone
from pathlib import Path

import protocole
from client_api import ClientApi, Statut
from liaison_serie import LiaisonBoitier
from tampon import Tampon

journal = logging.getLogger("passerelle")

TAILLE_LOT = 20
ATTENTE_SI_VIDE_S = 2.0
ATTENTE_MIN_S = 5.0
ATTENTE_MAX_S = 300.0
SUSPENSION_JETON_S = 300.0
# Fichier créé par systemd-timesyncd quand l'horloge est synchronisée (NTP).
FICHIER_SYNCHRO_NTP = Path("/run/systemd/timesync/synchronized")


class ErreurConfiguration(Exception):
    pass


def charger_configuration(chemin: str) -> dict:
    try:
        with open(chemin, "rb") as fichier:
            config = tomllib.load(fichier)
    except FileNotFoundError as exc:
        raise ErreurConfiguration(f"fichier introuvable : {chemin}") from exc
    except tomllib.TOMLDecodeError as exc:
        raise ErreurConfiguration(f"TOML invalide : {exc}") from exc

    api = config.get("api", {})
    url = api.get("url", "")
    if url.startswith("http://"):
        # EF-C-04 impose HTTPS. Le HTTP n'est toléré que pour tester sur le
        # réseau local, et seulement si on le demande explicitement.
        if not api.get("autoriser_http_pour_tests", False):
            raise ErreurConfiguration(
                "l'URL de l'API doit être en https:// "
                "(autoriser_http_pour_tests = true pour un test local)"
            )
        journal.warning("ATTENTION : envoi en HTTP non chiffré (mode test)")
    elif not url.startswith("https://"):
        raise ErreurConfiguration("api.url doit commencer par https://")

    boitiers = config.get("boitiers", [])
    if not boitiers:
        raise ErreurConfiguration("aucun [[boitiers]] déclaré")
    noms = set()
    for boitier in boitiers:
        for cle in ("nom", "port", "jeton"):
            if not boitier.get(cle):
                raise ErreurConfiguration(f"boîtier incomplet : '{cle}' manquant")
        if boitier["nom"] in noms:
            raise ErreurConfiguration(f"nom de boîtier en double : {boitier['nom']}")
        noms.add(boitier["nom"])

    intervalle = config.get("passerelle", {}).get("intervalle_mesure_s", 300)
    if not 5 <= intervalle <= 3600:
        raise ErreurConfiguration("intervalle_mesure_s doit être entre 5 et 3600")
    return config


def horloge_synchronisee() -> bool:
    return FICHIER_SYNCHRO_NTP.exists()


class Passerelle:
    def __init__(self, config: dict):
        reglages = config.get("passerelle", {})
        api = config["api"]
        self._arret = threading.Event()
        self._tampon = Tampon(
            reglages.get("tampon", "/var/lib/passerelle-serre/tampon.sqlite3"),
            reglages.get("tampon_max", 20000),
        )
        self._client = ClientApi(
            api["url"], api.get("verifier_tls", True), api.get("delai_s", 10)
        )
        intervalle = reglages.get("intervalle_mesure_s", 300)
        self._jetons = {b["nom"]: b["jeton"] for b in config["boitiers"]}
        self._liaisons = {
            b["nom"]: LiaisonBoitier(
                b["nom"], b["port"], intervalle, self._sur_mesure, self._arret
            )
            for b in config["boitiers"]
        }
        self._horloge_ok = None

    # --- appelé par les threads série ---
    def _sur_mesure(self, boitier: str, message: dict) -> None:
        self._verifier_horloge()
        corps = {
            # uuid : permet à l'API d'ignorer un doublon si on renvoie une
            # mesure déjà enregistrée (réponse perdue à cause d'un timeout).
            "uuid": str(uuid.uuid4()),
            # Horodatage en UTC au moment de la réception (l'Arduino n'a
            # pas d'horloge). Format ISO 8601 avec 'Z'.
            "mesure_le": datetime.now(timezone.utc)
            .replace(microsecond=0)
            .isoformat()
            .replace("+00:00", "Z"),
            **protocole.extraire_grandeurs(message),
        }
        perdues = self._tampon.ajouter(boitier, corps)
        journal.info(
            "[%s] mesure n°%s : T=%s °C  H=%s %%  L=%s lx",
            boitier,
            message["seq"],
            corps["temperature"],
            corps["humidite"],
            corps["luminosite"],
        )
        if perdues:
            journal.warning("tampon plein : %d mesure(s) ancienne(s) supprimée(s)", perdues)

    def _verifier_horloge(self) -> None:
        ok = horloge_synchronisee()
        if ok != self._horloge_ok:
            if ok:
                journal.info("horloge synchronisée (NTP)")
            else:
                journal.warning(
                    "horloge NON synchronisée : les dates des mesures peuvent être fausses"
                )
            self._horloge_ok = ok

    # --- boucle principale ---
    def demarrer(self) -> None:
        journal.info(
            "démarrage : %d boîtier(s), %d mesure(s) en attente dans le tampon",
            len(self._liaisons),
            self._tampon.compter(),
        )
        for liaison in self._liaisons.values():
            liaison.start()
        try:
            self._boucle_envoi()
        finally:
            for liaison in self._liaisons.values():
                liaison.join(timeout=5)
            self._tampon.fermer()
            journal.info("arrêt terminé")

    def arreter(self, *_):
        journal.info("arrêt demandé")
        self._arret.set()

    def _boucle_envoi(self) -> None:
        attente = ATTENTE_MIN_S
        suspendus = {}  # nom du boîtier -> instant de fin de suspension

        while not self._arret.is_set():
            maintenant = self._maintenant()
            suspendus = {n: fin for n, fin in suspendus.items() if fin > maintenant}
            lot = self._tampon.plus_anciennes(TAILLE_LOT, exclure=suspendus.keys())
            if not lot:
                self._arret.wait(ATTENTE_SI_VIDE_S)
                continue

            panne_reseau = False
            for mesure in lot:
                if self._arret.is_set() or mesure.boitier in suspendus:
                    continue
                jeton = self._jetons.get(mesure.boitier)
                if jeton is None:
                    journal.warning(
                        "mesure d'un boîtier retiré de la config (%s), supprimée",
                        mesure.boitier,
                    )
                    self._tampon.supprimer(mesure.id)
                    continue

                resultat = self._client.envoyer(jeton, mesure.corps)
                if resultat.statut is Statut.ACCEPTEE:
                    self._tampon.supprimer(mesure.id)
                    attente = ATTENTE_MIN_S
                    if resultat.alerte_active is not None:
                        self._liaisons[mesure.boitier].definir_alerte(
                            resultat.alerte_active
                        )
                elif resultat.statut is Statut.REJETEE:
                    journal.error(
                        "[%s] mesure refusée par l'API (HTTP %s) : %s",
                        mesure.boitier,
                        resultat.code_http,
                        resultat.detail,
                    )
                    self._tampon.supprimer(mesure.id)
                elif resultat.statut is Statut.NON_AUTORISEE:
                    journal.error(
                        "[%s] jeton refusé (HTTP %s) : révoqué ou incorrect ? "
                        "Mesures conservées, nouvel essai dans %d s",
                        mesure.boitier,
                        resultat.code_http,
                        SUSPENSION_JETON_S,
                    )
                    suspendus[mesure.boitier] = self._maintenant() + SUSPENSION_JETON_S
                else:
                    self._tampon.noter_tentative(mesure.id)
                    journal.warning(
                        "API injoignable (%s), %d mesure(s) en attente, "
                        "nouvel essai dans %.0f s",
                        resultat.code_http or resultat.detail,
                        self._tampon.compter(),
                        attente,
                    )
                    panne_reseau = True
                    break

            if panne_reseau:
                self._arret.wait(attente)
                attente = min(attente * 2, ATTENTE_MAX_S)  # recul exponentiel

    @staticmethod
    def _maintenant() -> float:
        return time.monotonic()


def main() -> int:
    analyseur = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    analyseur.add_argument("--config", default="/etc/passerelle-serre/config.toml")
    analyseur.add_argument("-v", "--verbeux", action="store_true")
    args = analyseur.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.verbeux else logging.INFO,
        format="%(levelname)s %(name)s: %(message)s",  # journald ajoute la date
    )
    try:
        config = charger_configuration(args.config)
    except ErreurConfiguration as exc:
        journal.error("configuration : %s", exc)
        return 2

    passerelle = Passerelle(config)
    signal.signal(signal.SIGTERM, passerelle.arreter)  # systemctl stop
    signal.signal(signal.SIGINT, passerelle.arreter)   # Ctrl+C
    passerelle.demarrer()
    return 0


if __name__ == "__main__":
    sys.exit(main())
