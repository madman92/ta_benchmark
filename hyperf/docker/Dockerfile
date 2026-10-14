FROM phpswoole/swoole:php8.5-zts AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
WORKDIR /app

FROM runtime AS builder
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM runtime
COPY --from=builder /app/vendor /app/vendor
COPY . .
RUN mkdir -p runtime/logs
CMD ["php", "bin/hyperf.php", "start"]
