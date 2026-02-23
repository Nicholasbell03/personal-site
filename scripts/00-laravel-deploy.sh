#!/usr/bin/env bash
echo "Running migrations..."
php artisan migrate --force

echo "Running production seeders..."
php artisan db:seed --class=ProductionSeeder --force

echo "Processing one-time operations..."
php artisan operations:process

# Read APP_ENV from .env if not already set as a shell environment variable
# (On Render, APP_ENV is a real env var; locally, it only exists in .env)
if [ -z "$APP_ENV" ] && [ -f .env ]; then
    APP_ENV=$(grep '^APP_ENV=' .env 2>/dev/null | cut -d= -f2)
fi

# Only cache in production — cached config prevents phpunit.xml env overrides
# from taking effect, which breaks the test suite locally
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache

    echo "Caching routes..."
    php artisan route:cache

    echo "Caching events..."
    php artisan event:cache

    echo "Caching views..."
    php artisan view:cache
else
    echo "Skipping cache (APP_ENV=${APP_ENV:-not set}, not production)"
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan event:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
fi

echo "Publishing Log Viewer assets..."
php artisan vendor:publish --tag=log-viewer-assets --force

echo "Fixing storage permissions..."
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
