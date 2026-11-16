#!/usr/bin/env python3
"""FAUSSE API pour tester la passerelle avant que Laravel soit prêt.

Ce n'est PAS l'API du projet : elle affiche ce qu'elle reçoit et imite
les réponses prévues (201, 401, 422) pour vérifier le comportement de la
passerelle. À lancer sur le Pi lui-même ou sur ton PC.

    python3 outils/faux_api.py --port 8000 --jeton test123 --seuil-temperature 26

Avec --seuil-temperature, la réponse contient "alerte_active": true quand
la température dépasse le seuil : prends le DHT22 dans ta main pour faire
clignoter la LED et sonner le buzzer.
"""

import argparse
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PLAGES = {"temperature": (-40, 80), "humidite": (0, 100), "luminosite": (0, 65535)}


def creer_gestionnaire(jeton_attendu, seuil_temperature):
    deja_recus = set()

    class Gestionnaire(BaseHTTPRequestHandler):
        def do_POST(self):
            if self.path != "/api/v1/mesures":
                return self._repondre(404, {"message": "route inconnue"})
            if self.headers.get("Authorization") != f"Bearer {jeton_attendu}":
                return self._repondre(401, {"message": "jeton invalide"})
            longueur = int(self.headers.get("Content-Length", 0))
            try:
                corps = json.loads(self.rfile.read(longueur))
            except json.JSONDecodeError:
                return self._repondre(400, {"message": "JSON invalide"})

            erreurs = {}
            for grandeur, (mini, maxi) in PLAGES.items():
                valeur = corps.get(grandeur)
                if valeur is not None and not mini <= valeur <= maxi:
                    erreurs[grandeur] = [f"doit être entre {mini} et {maxi}"]
            if erreurs:
                print(f"  -> 422 {erreurs}")
                return self._repondre(422, {"message": "hors plage", "errors": erreurs})

            doublon = corps.get("uuid") in deja_recus
            deja_recus.add(corps.get("uuid"))
            temperature = corps.get("temperature")
            alerte = (
                seuil_temperature is not None
                and temperature is not None
                and temperature > seuil_temperature
            )
            print(
                f"{'DOUBLON ' if doublon else ''}{corps.get('mesure_le')}  "
                f"T={temperature}  H={corps.get('humidite')}  "
                f"L={corps.get('luminosite')}  alerte={alerte}"
            )
            self._repondre(200 if doublon else 201, {"alerte_active": alerte})

        def _repondre(self, code, donnees):
            contenu = json.dumps(donnees).encode()
            self.send_response(code)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(contenu)))
            self.end_headers()
            self.wfile.write(contenu)

        def log_message(self, *args):
            pass  # on affiche nos propres lignes, plus lisibles

    return Gestionnaire


def main():
    analyseur = argparse.ArgumentParser()
    analyseur.add_argument("--hote", default="0.0.0.0")
    analyseur.add_argument("--port", type=int, default=8000)
    analyseur.add_argument("--jeton", default="test123")
    analyseur.add_argument("--seuil-temperature", type=float, default=None)
    args = analyseur.parse_args()

    serveur = ThreadingHTTPServer(
        (args.hote, args.port), creer_gestionnaire(args.jeton, args.seuil_temperature)
    )
    print(f"Fausse API sur http://{args.hote}:{args.port}/api/v1/mesures (Ctrl+C pour arrêter)")
    try:
        serveur.serve_forever()
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
