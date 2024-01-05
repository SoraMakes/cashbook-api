#!/bin/bash

# apply php ini settings
echo upload_max_filesize=$PHP_UPLOAD_MAX_FILESIZE > /usr/local/etc/php/conf.d/uploads.ini
echo post_max_size=$PHP_POST_MAX_SIZE >> /usr/local/etc/php/conf.d/uploads.ini

# ensure storage folder is writable. Owner and group ids are wrong after update from alpine image
chown -R www-data:www-data /var/www/html/storage

# wait for db to start
# setup variables
set -a
curenv=$(declare -p -x)
source .env
eval "$curenv"
set +a
# ping db
while ! mariadb-admin ping -h$DB_HOST -P$DB_PORT -u$DB_USERNAME -p$DB_PASSWORD --silent 2>/dev/null; do echo "db is starting" && sleep 1; done
echo "db is up"

# Run Laravel migrations
su www-data -s /bin/sh -c "php /var/www/html/artisan migrate --force"

# Generate API documentation
su www-data -s /bin/sh -c "php /var/www/html/artisan scribe:generate"

# Start PHP-FPM in the background
php-fpm &

# Start Nginx in the foreground
exec nginx -g 'daemon off;'
