"""Tampon local des mesures (SQLite).

Principe « boîte d'envoi » : toute mesure reçue de l'Arduino est d'abord
écrite ici, puis l'envoyeur la transmet à l'API et la supprime seulement
quand l'API l'a acceptée. Une coupure Wi-Fi, un redémarrage du Pi ou une
panne du serveur ne fait donc perdre aucune mesure.

SQLite est fourni avec Python (aucune dépendance) et supporte bien les
coupures de courant. Une écriture toutes les 5 minutes par boîtier n'use
pas la carte SD de façon significative.
"""

import json
import sqlite3
import threading
from dataclasses import dataclass


@dataclass(frozen=True)
class MesureEnAttente:
    id: int
    boitier: str
    corps: dict
    tentatives: int


class Tampon:
    def __init__(self, chemin: str, taille_max: int = 20000):
        self._taille_max = taille_max
        self._verrou = threading.Lock()
        # check_same_thread=False : la connexion est partagée entre les
        # threads, l'accès est protégé par self._verrou.
        self._connexion = sqlite3.connect(
            chemin, check_same_thread=False, isolation_level=None
        )
        self._connexion.execute("PRAGMA journal_mode=WAL")
        self._connexion.execute("PRAGMA synchronous=NORMAL")
        self._connexion.execute(
            """
            CREATE TABLE IF NOT EXISTS mesures_en_attente (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                boitier TEXT NOT NULL,
                corps TEXT NOT NULL,
                tentatives INTEGER NOT NULL DEFAULT 0
            )
            """
        )

    def ajouter(self, boitier: str, corps: dict) -> int:
        """Ajoute une mesure. Retourne le nombre de mesures supprimées
        parce que le tampon était plein (les plus anciennes partent)."""
        with self._verrou:
            self._connexion.execute(
                "INSERT INTO mesures_en_attente (boitier, corps) VALUES (?, ?)",
                (boitier, json.dumps(corps)),
            )
            (total,) = self._connexion.execute(
                "SELECT COUNT(*) FROM mesures_en_attente"
            ).fetchone()
            surplus = total - self._taille_max
            if surplus > 0:
                self._connexion.execute(
                    "DELETE FROM mesures_en_attente WHERE id IN ("
                    "SELECT id FROM mesures_en_attente ORDER BY id LIMIT ?)",
                    (surplus,),
                )
                return surplus
            return 0

    def plus_anciennes(self, nombre: int, exclure=()) -> list[MesureEnAttente]:
        exclure = list(exclure)
        requete = "SELECT id, boitier, corps, tentatives FROM mesures_en_attente"
        if exclure:
            marqueurs = ",".join("?" for _ in exclure)
            requete += f" WHERE boitier NOT IN ({marqueurs})"
        requete += " ORDER BY id LIMIT ?"
        with self._verrou:
            lignes = self._connexion.execute(requete, (*exclure, nombre)).fetchall()
        return [
            MesureEnAttente(id=i, boitier=b, corps=json.loads(c), tentatives=t)
            for (i, b, c, t) in lignes
        ]

    def supprimer(self, identifiant: int) -> None:
        with self._verrou:
            self._connexion.execute(
                "DELETE FROM mesures_en_attente WHERE id = ?", (identifiant,)
            )

    def noter_tentative(self, identifiant: int) -> None:
        with self._verrou:
            self._connexion.execute(
                "UPDATE mesures_en_attente SET tentatives = tentatives + 1 "
                "WHERE id = ?",
                (identifiant,),
            )

    def compter(self) -> int:
        with self._verrou:
            (total,) = self._connexion.execute(
                "SELECT COUNT(*) FROM mesures_en_attente"
            ).fetchone()
        return total

    def fermer(self) -> None:
        with self._verrou:
            self._connexion.close()
