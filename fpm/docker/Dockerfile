FROM composer:latest AS composer

FROM php:8.5-fpm-alpine

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/views storage/framework/sessions bootstrap/cache

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY . .
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache