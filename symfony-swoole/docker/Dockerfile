FROM phpswoole/swoole:php8.5-zts AS builder

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json symfony.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM phpswoole/swoole:php8.5-zts

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app
COPY --from=builder /app/vendor /app/vendor
COPY --from=builder /app/composer.lock /app/composer.lock
COPY . .
