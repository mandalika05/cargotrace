# ============================================================
# BUAT APA : Membangun image CargoTrace untuk hosting (Render, atau Docker di mana saja). P15.
# TAHAP    : 1) "assets"  -> build CSS/JS dengan Node (Vite + Tailwind)
#            2) image akhir -> PHP 8.3 + Apache, composer install, hasil build tahap 1
# JALAN    : docker/entrypoint.sh (cache config, migrasi, data demo bila kosong, lalu Apache)
# PORT     : mengikuti variabel PORT dari hosting (default 10000)
# ============================================================

# ---------- Tahap 1: build aset front-end ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- Tahap 2: PHP + Apache ----------
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && docker-php-ext-install pdo_mysql bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Apache melayani folder public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'opcache.enable=1\nopcache.validate_timestamps=0\nexpose_php=0\n' > /usr/local/etc/php/conf.d/cargotrace.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
# Paket dev (Faker) SENGAJA ikut dipasang: seeder data demo memakai factory yang butuh fake().
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

# Jaga-jaga untuk hosting yang menimpa perintah start dengan "apache2-foreground"
# (contoh: SnapDeploy). Perintah asli dipindah jadi apache2-foreground-real, dan
# "apache2-foreground" diganti pembungkus yang menjalankan entrypoint kita dulu
# (atur port, cache, migrasi, data demo) sebelum Apache menyala.
RUN mv /usr/local/bin/apache2-foreground /usr/local/bin/apache2-foreground-real \
    && printf '#!/bin/sh\nexec /var/www/html/docker/entrypoint.sh\n' > /usr/local/bin/apache2-foreground \
    && chmod +x /usr/local/bin/apache2-foreground

EXPOSE 10000
ENTRYPOINT ["docker/entrypoint.sh"]
