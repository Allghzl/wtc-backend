#!/bin/sh

set -e

echo "Clearing Laravel caches..."
php artisan optimize:clear

echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

echo "Starting application..."
exec "$@"