FROM trueasync/php-true-async:0.8.9-php8.6-frankenphp AS builder

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM trueasync/php-true-async:0.8.9-php8.6-frankenphp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=builder /app/vendor /app/vendor
COPY --from=builder /app/composer.lock /app/composer.lock
COPY . .