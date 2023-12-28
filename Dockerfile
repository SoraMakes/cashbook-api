# Use Alpine-based PHP 8.1 image
FROM php:8.1-fpm-alpine

# default values for env variables
ENV PHP_UPLOAD_MAX_FILESIZE=${PHP_UPLOAD_MAX_FILESIZE:-"100M"}
ENV PHP_POST_MAX_SIZE=${PHP_POST_MAX_SIZE:-"100M"}


# Install system dependencies including ImageMagick, Ghostscript, and ICU libraries
RUN apk add --no-cache nginx imagemagick ghostscript icu-dev mysql-client

# Install PHP extensions required for Laravel/Lumen
RUN docker-php-ext-install pdo pdo_mysql
RUN apk add --no-cache icu-libs && docker-php-ext-install intl

# Install the build dependencies and Imagick
RUN apk add --no-cache imagemagick-dev autoconf g++ make \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del autoconf g++ make # Clean up unnecessary packages after installation

# Copy your application code to the container
COPY . /var/www/html

# Copy the default environment file
RUN mv /var/www/html/.env.default /var/www/html/.env

# Allow the web server user to write to storage directory
RUN chown -R www-data:www-data /var/www/html/storage

# Set working directory
WORKDIR /var/www/html

# Update ImageMagick policy for handling PDFs
RUN sed -i '/<policy domain="coder" rights="none" pattern="PDF" \/>/c\<policy domain="coder" rights="read|write" pattern="PDF" \/>' /etc/ImageMagick-7/policy.xml

# Copy your Nginx configuration file
COPY nginx.conf /etc/nginx/nginx.conf

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Replace or add process_control_timeout setting in php-fpm.conf to let PHP-FPM exit gracefully
RUN sed -i '/process_control_timeout/c\process_control_timeout = 5s' /usr/local/etc/php-fpm.conf

# Expose the port Nginx is reachable on
EXPOSE 80

# Startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Start PHP-FPM and Nginx
CMD ["/start.sh"]
