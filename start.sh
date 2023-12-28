#!/bin/sh

# apply php ini settings
echo $PHP_UPLOAD_MAX_FILESIZE > /usr/local/etc/php/conf.d/uploads.ini
echo $PHP_POST_MAX_SIZE >> /usr/local/etc/php/conf.d/uploads.ini

while ! mysqladmin ping -h$DB_HOST -P$DB_PORT -u$DB_USERNAME -p$DB_PASSWORD --silent 2>/dev/null; do echo "db is starting" && sleep 1; done
echo "db is up"

# Run Laravel migrations
su www-data -s /bin/sh -c "php /var/www/html/artisan migrate --force"

# Generate API documentation
su www-data -s /bin/sh -c "php /var/www/html/artisan scribe:generate"

# Start PHP-FPM in the background
php-fpm &

# Start Nginx in the foreground
exec nginx -g 'daemon off;'
