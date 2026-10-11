#!/bin/sh
set -eu

php artisan route:clear
php artisan config:cache
php artisan route:cache

exec "$@"
