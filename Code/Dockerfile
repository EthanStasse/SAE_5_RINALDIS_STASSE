# Image de PRODUCTION de l'application Cocagne (multi-stage build).
# Construction, depuis le dossier Code/ :
#   docker build -t cocagne:local .
#
# Quatre étapes ; seule la dernière forme l'image livrée. Les outils de
# construction (composer, Node, npm) n'y sont pas : image plus petite et
# moins de logiciels exposés en cas d'attaque.
#
#   base       PHP 8.3 FPM + extensions (socle commun)
#   vendor     dépendances PHP sans les paquets de développement
#   front      compilation du CSS et du JS (Vite + Tailwind)
#   production code + vendor + front compilé, utilisateur non root

# --- 1. base : PHP et ses extensions -----------------------------------------
# Même version que le développement (php:8.3-fpm, Debian) pour éviter les
# écarts entre le poste et le serveur.
FROM php:8.3-fpm AS base

# install-php-extensions installe chaque extension avec ses seules
# bibliothèques d'exécution, sans laisser les paquets de compilation (-dev).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath opcache

WORKDIR /var/www/html

# --- 2. vendor : dépendances PHP --------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

# D'abord composer.json et composer.lock seuls : tant qu'ils ne changent pas,
# Docker réutilise cette couche et ne retélécharge pas les paquets.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

# Puis le code, et l'autoloader optimisé (table de classes générée une fois
# pour toutes, plus rapide que la recherche de fichiers à chaque requête).
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# --- 3. front : CSS et JavaScript -------------------------------------------
FROM node:22-alpine AS front

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
# resources/css/app.css demande à Tailwind de lire les vues de pagination de
# Laravel, qui sont dans vendor/ : on les reprend de l'étape vendor, sinon
# leurs classes CSS manqueraient sans aucun message d'erreur.
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# --- 4. production : l'image livrée -----------------------------------------
FROM base AS production

# Outil cgi-fcgi pour le healthcheck (PHP-FPM ne parle pas HTTP).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libfcgi-bin \
    && rm -rf /var/lib/apt/lists/*

# Réglages PHP de production (erreurs non affichées), opcache, page /ping de FPM.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/zz-opcache.ini"
COPY docker/php/fpm-ping.conf /usr/local/etc/php-fpm.d/zz-ping.conf

# Code + vendor (étape 2) et front compilé (étape 3), appartenant à www-data.
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=front --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Dossiers d'écriture de Laravel (leur contenu est exclu par .dockerignore).
RUN mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Utilisateur non root : si l'application est compromise, l'attaquant n'a pas
# les droits root dans le conteneur.
USER www-data

# Sain si PHP-FPM répond « pong » à la page /ping.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

EXPOSE 9000
CMD ["php-fpm"]
