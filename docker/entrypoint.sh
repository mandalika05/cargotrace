#!/bin/sh
# ============================================================
# BUAT APA : Dijalankan setiap container start (P15).
#   1) Apache mendengarkan di port dari hosting ($PORT, default 10000)
#   2) cache konfigurasi dan view supaya cepat
#   3) migrasi database bila RUN_MIGRATIONS=true
#   4) isi data demo HANYA bila database masih kosong, dan hanya bila SEED_DEMO=true
#   5) jalankan Apache
# ============================================================
set -e

PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi || true
php artisan config:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan cargotrace:seed-if-empty
fi

# pakai perintah asli (bukan pembungkus apache2-foreground di Dockerfile) supaya tidak berputar
exec apache2-foreground-real
