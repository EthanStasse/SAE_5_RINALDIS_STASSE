#!/usr/bin/env bash
# Démarre l'environnement de développement.
# Première fois : crée le projet Laravel, installe les paquets, prépare la base.
# Les fois suivantes : vérifie les dépendances, migre et relance les conteneurs.
set -euo pipefail

export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"

docker compose build
docker compose run --rm app bash docker/init-laravel.sh
docker compose up -d

echo
echo "Application : http://localhost:8080"
echo "E-mails     : http://localhost:8025 (Mailpit)"
echo "Base        : localhost:5433, base cocagne, utilisateur cocagne"
