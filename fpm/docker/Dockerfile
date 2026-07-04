FROM composer:latest AS composer

FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql opcache

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app
