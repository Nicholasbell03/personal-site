#!/usr/bin/env bash
echo "Caching config..."
php artisan optimize

echo "Caching views..."
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force
