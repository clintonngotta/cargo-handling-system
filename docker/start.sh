#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force

# Seeds the demo tanks, movements and operator only into an empty database.
if [ "${SEED_DEMO_DATA:-false}" = "true" ] \
    && [ "$(php artisan tinker --execute='echo App\Models\User::query()->count();')" = "0" ]; then
    php artisan db:seed --force
fi

exec frankenphp php-server --root public/ --listen ":${PORT:-8080}"
