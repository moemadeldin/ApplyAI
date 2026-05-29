#!/bin/sh
set -e

cd /var/www/html

chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force --isolated

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
