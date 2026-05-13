# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-redis --ignore-platform-req=ext-intl

FROM php:8.5-fpm-alpine AS runtime
# Install runtime libs first so they are marked as explicit packages and
# survive the subsequent `apk del .build-deps` step.
RUN apk add --no-cache \
        mysql-client bash \
        libzip oniguruma icu-libs \
 && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev oniguruma-dev zlib-dev icu-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath zip pcntl mbstring intl opcache \
 && pecl install redis \
 && docker-php-ext-enable redis opcache \
 && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

CMD ["php-fpm"]
