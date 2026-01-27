#!/usr/bin/env bash
echo "Running composer"
composer install --no-dev --working-dir=/var/www/html

echo "Caching config..."
php artisan optimize

echo "Caching views..."
php artisan view:cache

echo "Publishing Filament assets..."
php artisan filament:assets

echo "Running migrations..."
php artisan migrate --force