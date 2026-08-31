#!/bin/sh
set -e

# Cache Laravel bootstrap artifacts.
# These are safe to run before migrations — they only serialize
# config, routes, and Blade templates to PHP files.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
