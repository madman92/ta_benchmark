FROM composer:latest AS composer

FROM php:8.5-fpm-alpine

# The base image enables access logging; match the other benchmark servers.
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && sed -i '/^[[:space:]]*access[.]log[[:space:]]*=/d' /usr/local/etc/php-fpm.d/docker.conf

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/views storage/framework/sessions bootstrap/cache

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY . .
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/laravel-entrypoint
RUN chmod +x /usr/local/bin/laravel-entrypoint

ENTRYPOINT ["/usr/local/bin/laravel-entrypoint"]
CMD ["php-fpm"]
