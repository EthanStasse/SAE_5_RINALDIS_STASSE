"""Client HTTP vers l'API Laravel (POST /api/v1/mesures).

Contrat attendu côté Laravel (à implémenter aux étapes suivantes) :

    POST /api/v1/mesures
    Authorization: Bearer <jeton du boîtier>
    Accept: application/json
    Content-Type: application/json

    {
      "uuid": "6f1c...",                 # identifiant unique généré par le Pi
      "mesure_le": "2026-11-16T09:55:00Z",  # UTC (EF-C-04)
      "temperature": 21.4,               # null si capteur en erreur
      "humidite": 55.2,
      "luminosite": 340.0
    }

    201 Created              -> mesure enregistrée
    200 OK                   -> uuid déjà reçu (renvoi après un timeout) : idempotent
    401 / 403                -> jeton absent, invalide ou révoqué
    422 Unprocessable Entity -> valeur hors plage plausible (EF-C-05)

    Corps de réponse 200/201 (facultatif) : {"alerte_active": true|false}
    -> sert à allumer la LED et le buzzer du boîtier.
"""

import enum
from dataclasses import dataclass

import requests


class Statut(enum.Enum):
    ACCEPTEE = "acceptee"            # supprimer du tampon
    REJETEE = "rejetee"              # 4xx définitif : supprimer et journaliser
    NON_AUTORISEE = "non_autorisee"  # 401/403 : garder, suspendre ce boîtier
    A_REESSAYER = "a_reessayer"      # réseau, 5xx, 429 : garder, réessayer plus tard


@dataclass
class Resultat:
    statut: Statut
    code_http: int | None = None
    detail: str = ""
    alerte_active: bool | None = None


class ClientApi:
    def __init__(self, url: str, verifier_tls, delai_s: float = 10.0):
        self._url = url
        self._verifier_tls = verifier_tls  # True, False ou chemin vers un certificat CA
        self._delai_s = delai_s
        self._session = requests.Session()
        self._session.headers.update(
            {
                "Accept": "application/json",
                "User-Agent": "passerelle-serre/1.0",
            }
        )

    def envoyer(self, jeton: str, corps: dict) -> Resultat:
        try:
            reponse = self._session.post(
                self._url,
                json=corps,
                headers={"Authorization": f"Bearer {jeton}"},
                timeout=self._delai_s,
                verify=self._verifier_tls,
            )
        except requests.RequestException as exc:
            # Message court : le détail complet de requests est illisible dans le journal.
            return Resultat(Statut.A_REESSAYER, detail=type(exc).__name__)

        code = reponse.status_code
        if 200 <= code < 300:
            return Resultat(
                Statut.ACCEPTEE, code, alerte_active=_lire_alerte(reponse)
            )
        detail = reponse.text[:500]
        if code in (401, 403):
            return Resultat(Statut.NON_AUTORISEE, code, detail)
        if code in (408, 429) or code >= 500:
            return Resultat(Statut.A_REESSAYER, code, detail)
        # 400, 404, 422... : renvoyer la même mesure donnerait la même erreur.
        return Resultat(Statut.REJETEE, code, detail)


def _lire_alerte(reponse) -> bool | None:
    try:
        donnees = reponse.json()
    except ValueError:
        return None
    if isinstance(donnees, dict) and isinstance(donnees.get("alerte_active"), bool):
        return donnees["alerte_active"]
    return None
