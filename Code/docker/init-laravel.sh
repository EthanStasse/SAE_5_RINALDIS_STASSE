#!/usr/bin/env bash
# Exécuté DANS le conteneur app par ../demarrer.sh. Idempotent : peut être relancé.
set -euo pipefail
cd /var/www/html

# Décompactage des dépendances sur un volume Windows peut dépasser le délai
# par défaut de Composer (300 s).
export COMPOSER_PROCESS_TIMEOUT=2000

# 1. Squelette Laravel (uniquement la première fois)
if [ ! -f artisan ]; then
  echo "==> Création du squelette Laravel"
  composer create-project laravel/laravel /tmp/laravel --prefer-dist --no-interaction --no-scripts
  # Nos fichiers (déjà présents) sont conservés : seuls les fichiers absents sont copiés.
  rsync -a --ignore-existing /tmp/laravel/ /var/www/html/
  rm -rf /tmp/laravel
fi

# 2. Dépendances
if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction
fi

if ! grep -q '"spatie/laravel-permission"' composer.json; then
  echo "==> Installation des paquets du projet"
  composer require --no-interaction spatie/laravel-permission
  composer require --no-interaction --dev -W pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
fi

# 3. Fichier .env
[ -f .env ] || cp .env.example .env

set_env() {
  local key="$1" value="$2"
  if grep -qE "^#? ?${key}=" .env; then
    sed -i -E "s|^#? ?${key}=.*|${key}=${value}|" .env
  else
    echo "${key}=${value}" >> .env
  fi
}

set_env APP_NAME Cocagne
set_env APP_URL http://localhost:8080
set_env APP_LOCALE fr
set_env DB_CONNECTION pgsql
set_env DB_HOST db
set_env DB_PORT 5432
set_env DB_DATABASE cocagne
set_env DB_USERNAME cocagne
set_env DB_PASSWORD cocagne
set_env MAIL_MAILER smtp
set_env MAIL_HOST mailpit
set_env MAIL_PORT 1025

grep -q '^APP_KEY=base64' .env || php artisan key:generate --no-interaction

# 4. Permissions par structure (spatie/laravel-permission en mode "teams")
if ! ls database/migrations/*create_permission_tables.php >/dev/null 2>&1; then
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction
fi
sed -i "s/'teams' => false,/'teams' => true,/" config/permission.php
sed -i "s/'team_foreign_key' => 'team_id',/'team_foreign_key' => 'structure_id',/" config/permission.php
if ! grep -q "'teams' => true" config/permission.php || ! grep -q "'team_foreign_key' => 'structure_id'" config/permission.php; then
  echo "ERREUR : impossible d'activer le mode teams dans config/permission.php. Modifiez-le à la main." >&2
  exit 1
fi

# 5. Base de données et données de démonstration (seeders idempotents)
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

# 6. Vérification
./vendor/bin/pest

echo "==> Environnement prêt."
