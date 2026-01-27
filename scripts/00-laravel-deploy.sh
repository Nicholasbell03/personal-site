#!/usr/bin/env bash
echo "Caching config..."
php artisan optimize

echo "Caching views..."
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Running production seeders..."
php artisan db:seed --class=ProductionSeeder --force
