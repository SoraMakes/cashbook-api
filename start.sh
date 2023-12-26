#!/bin/sh

# Run Laravel migrations
php artisan migrate

# Generate API documentation
php artisan scribe:generate

# Start PHP-FPM in the background
php-fpm &

# Start Nginx in the foreground
exec nginx -g 'daemon off;'
