FROM php:8.1-fpm-alpine


RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

COPY . .

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN apk add --no-cache \
    php-pdo \
    php-mbstring \
    php-openssl \
    php-intl \
    php-xml \
    php-mysqli

CMD ["/var/www/html/start.sh"]



