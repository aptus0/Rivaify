FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Modules/*/composer.json must be present before `composer install` runs —
# the wikimedia/composer-merge-plugin (required by nwidart/laravel-modules)
# merges each module's autoload mapping into vendor/composer/autoload_*.php
# at install time. Without this, the built image's autoloader silently
# lacks the Modules\* namespaces even though the module source is fine,
# which only surfaces at runtime as "Class ...ServiceProvider not found".
COPY Modules ./Modules
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --no-scripts --ignore-platform-req=ext-pcntl

FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-rivaify.ini

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
