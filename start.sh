#!/bin/sh

while ! mysqladmin ping -h$DB_HOST -P$DB_PORT -u$DB_USERNAME -p$DB_PASSWORD --silent 2>/dev/null; do echo "db is starting" && sleep 1; done
echo "db is up"

# Run Laravel migrations
php artisan migrate --force

# Generate API documentation
php artisan scribe:generate

# Start PHP-FPM in the background
php-fpm &

# Start Nginx in the foreground
exec nginx -g 'daemon off;'
