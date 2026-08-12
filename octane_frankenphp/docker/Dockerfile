FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pcntl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app