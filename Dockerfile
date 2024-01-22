# Use Debian-based PHP 8.3 image
FROM php:8.3-fpm-bookworm

# Default values for environment variables
ENV PHP_UPLOAD_MAX_FILESIZE=${PHP_UPLOAD_MAX_FILESIZE:-"100M"}
ENV PHP_POST_MAX_SIZE=${PHP_POST_MAX_SIZE:-"100M"}

# Set this variable to the user nginx/fpm is serving the appication as
ENV APP_USER="www-data"

# Set frontend to noninteractive to skip any interactive post-install configuration steps
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies including ImageMagick, Ghostscript, and ICU libraries, build libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx imagemagick ghostscript libicu-dev mariadb-client unzip \
    libzip-dev libxml2-dev libonig-dev libmagickwand-dev autoconf g++ make

# Install PHP extensions required for Laravel/Lumen
RUN docker-php-ext-install pdo pdo_mysql intl zip dom mbstring

# Install the build dependencies and Imagick
RUN pecl install imagick && docker-php-ext-enable imagick

# Clean up
RUN apt-get remove -y autoconf g++ make && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy your application code to the container
COPY --chown=$APP_USER . /var/www/html

# Copy the default environment file
RUN mv /var/www/html/.env.default /var/www/html/.env

# Allow the web server user to write to storage directory
RUN chown -R $APP_USER:$APP_USER /var/www/html/storage

# Set working directory
WORKDIR /var/www/html

# Update ImageMagick policy for handling PDFs
RUN sed -i '/<policy domain="coder" rights="none" pattern="PDF" \/>/c\<policy domain="coder" rights="read|write" pattern="PDF" \/>' /etc/ImageMagick-6/policy.xml

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
