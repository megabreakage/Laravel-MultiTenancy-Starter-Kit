# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.5-fpm-alpine AS runtime
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        icu-dev oniguruma-dev libzip-dev zlib-dev libpng-dev \
 && apk add --no-cache mysql-client bash \
 && docker-php-ext-install pdo_mysql opcache intl bcmath zip gd \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["php-fpm"]
