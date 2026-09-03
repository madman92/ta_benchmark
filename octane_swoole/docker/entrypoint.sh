#!/bin/sh
set -eu

php artisan config:cache
php artisan route:cache

exec "$@"
