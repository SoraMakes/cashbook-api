#!/bin/sh

composer install --no-dev --optimize-autoloader

php artisan migrate
php artisan scribe:generate

php -S 0.0.0.0:8001 -t public

