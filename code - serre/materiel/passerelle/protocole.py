"""Analyse des lignes envoyées par l'Arduino et construction des commandes.

Ce module ne fait aucune entrée/sortie : il est donc facile à tester
(voir tests/test_protocole.py).
"""

import json

VERSION_PROTOCOLE = 1
GRANDEURS = ("temperature", "humidite", "luminosite")
TYPES_CONNUS = {"demarrage", "mesure", "pong", "ack", "erreur"}


class LigneInvalide(ValueError):
    """La ligne reçue n'est pas un message valide du protocole."""


def analyser_ligne(ligne: str) -> dict:
    """Transforme une ligne série en dictionnaire validé.

    Lève LigneInvalide si la ligne n'est pas du JSON valide ou ne respecte
    pas le protocole. Les lignes parasites sont normales au démarrage
    (octets envoyés pendant la réinitialisation de l'Uno) : l'appelant
    les journalise et les ignore.
    """
    ligne = ligne.strip()
    if not ligne:
        raise LigneInvalide("ligne vide")
    try:
        message = json.loads(ligne)
    except json.JSONDecodeError as exc:
        raise LigneInvalide(f"JSON invalide : {exc.msg}") from exc
    if not isinstance(message, dict):
        raise LigneInvalide("le message n'est pas un objet JSON")

    type_message = message.get("type")
    if type_message not in TYPES_CONNUS:
        raise LigneInvalide(f"type inconnu : {type_message!r}")

    if type_message == "mesure":
        _valider_mesure(message)
    return message


def _valider_mesure(message: dict) -> None:
    seq = message.get("seq")
    if not isinstance(seq, int) or isinstance(seq, bool) or seq < 1:
        raise LigneInvalide("seq absent ou invalide")
    for grandeur in GRANDEURS:
        if grandeur not in message:
            raise LigneInvalide(f"champ manquant : {grandeur}")
        valeur = message[grandeur]
        if valeur is not None and (
            isinstance(valeur, bool) or not isinstance(valeur, (int, float))
        ):
            raise LigneInvalide(f"{grandeur} n'est pas un nombre")
    if all(message[g] is None for g in GRANDEURS):
        raise LigneInvalide("aucun capteur n'a répondu")


def extraire_grandeurs(message: dict) -> dict:
    """Ne garde que les grandeurs mesurées (les null restent null).

    La validation des plages plausibles (EF-C-05) est faite par l'API,
    pas ici : la règle métier doit exister à un seul endroit.
    """
    return {g: message[g] for g in GRANDEURS}


def commande_ping() -> str:
    return "PING\n"


def commande_mesure() -> str:
    return "MESURE\n"


def commande_intervalle(secondes: int) -> str:
    if not 5 <= secondes <= 3600:
        raise ValueError("l'intervalle doit être compris entre 5 et 3600 s")
    return f"INTERVALLE {secondes}\n"


def commande_alerte(active: bool) -> str:
    return f"ALERTE {1 if active else 0}\n"
