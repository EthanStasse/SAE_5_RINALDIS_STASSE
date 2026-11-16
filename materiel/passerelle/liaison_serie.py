"""Liaison série avec un Arduino, avec reconnexion automatique.

Un thread par boîtier. Il :
  - ouvre le port (et le rouvre si le câble USB est débranché/rebranché) ;
  - attend la fin de la réinitialisation de l'Uno ;
  - envoie l'intervalle de mesure configuré ;
  - lit les lignes JSON et transmet les mesures au callback ;
  - écrit les commandes en attente (ALERTE...) ;
  - force une reconnexion si l'Arduino ne dit plus rien (chien de garde).
"""

import logging
import queue
import threading
import time

import serial

import protocole

VITESSE_BAUDS = 115200
# L'Uno redémarre quand on ouvre le port (signal DTR) : son bootloader
# occupe environ 2 s. Le Leonardo ne redémarre pas, l'attente est sans effet.
ATTENTE_APRES_OUVERTURE_S = 2.5
ATTENTE_AVANT_RECONNEXION_S = 5.0


class LiaisonBoitier(threading.Thread):
    def __init__(self, nom, port, intervalle_s, sur_mesure, arret: threading.Event):
        super().__init__(name=f"serie-{nom}", daemon=True)
        self.nom = nom
        self.port = port
        self.intervalle_s = intervalle_s
        self._sur_mesure = sur_mesure  # fonction(nom_boitier, message)
        self._arret = arret
        self._commandes = queue.Queue()
        self._journal = logging.getLogger(f"boitier.{nom}")
        # Si plus rien n'arrive pendant 2 intervalles + 60 s, on reconnecte.
        # Sur l'Uno, rouvrir le port le réinitialise : utile s'il est bloqué.
        self._silence_max_s = 2 * intervalle_s + 60
        self.derniere_alerte_envoyee = None

    def envoyer_commande(self, commande: str) -> None:
        """Peut être appelé depuis un autre thread (l'envoyeur)."""
        self._commandes.put(commande)

    def definir_alerte(self, active: bool) -> None:
        if active != self.derniere_alerte_envoyee:
            self.envoyer_commande(protocole.commande_alerte(active))

    def run(self) -> None:
        while not self._arret.is_set():
            try:
                self._session()
            except (serial.SerialException, OSError) as exc:
                self._journal.warning("liaison série perdue : %s", exc)
            if not self._arret.is_set():
                self._arret.wait(ATTENTE_AVANT_RECONNEXION_S)

    def _session(self) -> None:
        self._journal.info("ouverture de %s", self.port)
        # timeout=1 : readline() rend la main chaque seconde, ce qui permet
        # d'écrire les commandes et de vérifier l'arrêt du service.
        with serial.Serial(
            self.port, VITESSE_BAUDS, timeout=1, exclusive=True
        ) as liaison:
            self._arret.wait(ATTENTE_APRES_OUVERTURE_S)
            liaison.reset_input_buffer()  # jette les octets parasites du démarrage
            self.derniere_alerte_envoyee = None  # l'Uno a peut-être redémarré
            self._ecrire(liaison, protocole.commande_intervalle(self.intervalle_s))
            self._ecrire(liaison, protocole.commande_ping())
            dernier_message = time.monotonic()

            while not self._arret.is_set():
                self._vider_commandes(liaison)
                brut = liaison.readline()
                if brut:
                    dernier_message = time.monotonic()
                    self._traiter(brut)
                elif time.monotonic() - dernier_message > self._silence_max_s:
                    self._journal.warning(
                        "aucun message depuis %d s, reconnexion", self._silence_max_s
                    )
                    return

    def _traiter(self, brut: bytes) -> None:
        texte = brut.decode("utf-8", errors="replace")
        try:
            message = protocole.analyser_ligne(texte)
        except protocole.LigneInvalide as exc:
            self._journal.debug("ligne ignorée (%s) : %r", exc, texte)
            return

        type_message = message["type"]
        if type_message == "mesure":
            self._sur_mesure(self.nom, message)
        elif type_message == "demarrage":
            self._journal.info("l'Arduino a (re)démarré")
            # Un Leonardo qui redémarre sans qu'on rouvre le port aurait
            # repris l'intervalle par défaut : on le renvoie.
            self.derniere_alerte_envoyee = None
            self.envoyer_commande(protocole.commande_intervalle(self.intervalle_s))
        elif type_message == "pong":
            self._journal.info(
                "Arduino OK (protocole v%s, intervalle %s s)",
                message.get("version"),
                message.get("intervalle"),
            )
            if message.get("version") != protocole.VERSION_PROTOCOLE:
                self._journal.error("version de protocole inattendue")
        elif type_message == "erreur":
            self._journal.error("l'Arduino signale : %s", message.get("message"))

    def _vider_commandes(self, liaison) -> None:
        while True:
            try:
                commande = self._commandes.get_nowait()
            except queue.Empty:
                return
            self._ecrire(liaison, commande)

    def _ecrire(self, liaison, commande: str) -> None:
        liaison.write(commande.encode("ascii"))
        liaison.flush()
        if commande.startswith("ALERTE"):
            self.derniere_alerte_envoyee = commande.strip().endswith("1")
        self._journal.debug("commande envoyée : %s", commande.strip())
