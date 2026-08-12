FROM dunglas/frankenphp:php8.5 AS builder

RUN install-php-extensions pcntl pdo_pgsql zip \
    && apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pcntl pdo_pgsql zip \
    && apt-get update && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app
COPY --from=builder /app/vendor /app/vendor
COPY --from=builder /app/composer.lock /app/composer.lock
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/views storage/framework/sessions storage/logs storage/octane bootstrap/cache