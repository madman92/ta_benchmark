FROM openswoole/openswoole:26.2-php8.5-alpine AS builder

RUN apk add --no-cache postgresql-dev libcurl curl-dev git unzip \
    && docker-php-ext-install pdo_pgsql pcntl curl sockets intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json symfony.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts --ignore-platform-req=ext-openswoole

FROM openswoole/openswoole:26.2-php8.5-alpine

RUN apk add --no-cache postgresql-dev libcurl curl-dev \
    && docker-php-ext-install pdo_pgsql pcntl curl sockets intl

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN echo "openswoole.use_fiber_context=On" > /usr/local/etc/php/conf.d/openswoole.ini

WORKDIR /app
COPY --from=builder /app/vendor /app/vendor
COPY --from=builder /app/composer.lock /app/composer.lock
COPY . .
