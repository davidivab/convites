#!/bin/sh
set -e

cd /var/www

echo "Convites API — starting..."

# --------------------------------------------
# 1. Wait for MySQL (Dokploy / compose)
# --------------------------------------------
if [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    MAX_TRIES=40
    COUNT=0
    until nc -z -w 5 "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "MySQL did not become ready after ${MAX_TRIES} attempts — aborting."
            exit 1
        fi
        echo "  attempt ${COUNT}/${MAX_TRIES}..."
        sleep 2
    done
    echo "MySQL is reachable"
fi

# --------------------------------------------
# 2. Storage / cache dirs (idempotent)
# --------------------------------------------
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# --------------------------------------------
# 3. Discover + migrate (fail-fast)
# --------------------------------------------
php artisan package:discover --ansi || true
php artisan migrate --force --no-interaction
php artisan storage:link --force 2>/dev/null || true

# --------------------------------------------
# 4. Production caches (incl. route:cache)
# --------------------------------------------
if [ "${APP_ENV}" = "production" ]; then
    echo "Caching config / routes / views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
else
    echo "Clearing caches (APP_ENV=${APP_ENV:-local})..."
    php artisan optimize:clear || true
fi

# --------------------------------------------
# 5. Re-own files created as root during boot
# --------------------------------------------
chown -R www-data:www-data storage bootstrap/cache

echo "Starting services..."
exec "$@"
