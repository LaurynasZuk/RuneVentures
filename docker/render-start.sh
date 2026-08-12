#!/bin/sh
set -eu

PORT="${PORT:-10000}"

if [ -z "${APP_KEY:-}" ] && [ -n "${APP_KEY_VALUE:-}" ]; then
    export APP_KEY="base64:${APP_KEY_VALUE}"
fi

if [ -z "${DB_URL:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
    export DB_URL="${DATABASE_URL}"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is missing. Set APP_KEY or APP_KEY_VALUE." >&2
    exit 1
fi

if [ -z "${DB_URL:-}" ]; then
    echo "Database URL is missing. Set DATABASE_URL or DB_URL." >&2
    exit 1
fi

php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan view:cache

sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
