#!/usr/bin/env bash
echo "Running composer"
composer install --no-dev --working-dir=/var/www/html

echo "Caching config..."
php artisan optimize

echo "Running migrations..."
php artisan migrate --force