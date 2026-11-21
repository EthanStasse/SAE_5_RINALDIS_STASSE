#!/usr/bin/env bash
# Démarre l'environnement de développement.
# Première fois : crée le projet Laravel, installe les paquets, prépare la base.
# Les fois suivantes : vérifie les dépendances, migre et relance les conteneurs.
set -euo pipefail

export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"

docker compose build
MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps --user root app chown "$HOST_UID:$HOST_GID" /var/www/html/vendor
docker compose run --rm app bash docker/init-laravel.sh

if ! docker compose run --rm --no-deps node sh -lc '
  test -f node_modules/.package-lock.json &&
  test -f public/build/manifest.json &&
  ! test package.json -nt node_modules/.package-lock.json &&
  ! test package-lock.json -nt node_modules/.package-lock.json
'; then
  echo "==> Installation des dépendances front-end et compilation des assets"
  MSYS_NO_PATHCONV=1 docker compose run --rm --no-deps --user root node chown "$HOST_UID:$HOST_GID" /var/www/html/node_modules
  docker compose run --rm node npm ci
  docker compose run --rm node npm run build
fi

docker compose up -d

echo
echo "Application : http://localhost:8080"
echo "E-mails     : http://localhost:8025 (Mailpit)"
echo "Base        : localhost:5433, base cocagne, utilisateur cocagne"
