# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-redis --ignore-platform-req=ext-intl

FROM php:8.5-fpm-alpine AS runtime
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        oniguruma-dev libzip-dev zlib-dev \
 && apk add --no-cache mysql-client bash libzip \
 && docker-php-ext-install pdo_mysql bcmath zip pcntl \
 && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["php-fpm"]
