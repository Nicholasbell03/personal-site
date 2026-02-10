#!/usr/bin/env bash
echo "Running migrations..."
php artisan migrate --force

echo "Running production seeders..."
php artisan db:seed --class=ProductionSeeder --force

echo "Processing one-time operations..."
php artisan operations:process

echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching events..."
php artisan event:cache

echo "Caching views..."
php artisan view:cache

echo "Publishing Log Viewer assets..."
php artisan vendor:publish --tag=log-viewer-assets --force
