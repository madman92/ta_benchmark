# =============================================================================
# Stage 1: Build PHP from source (ZTS + async + embed SAPI)
# =============================================================================
FROM ubuntu:24.04 AS php-builder

ARG PHP_SRC_REPO=https://github.com/true-async/php-src.git
ARG PHP_SRC_BRANCH=true-async-stable
ARG ASYNC_REPO=https://github.com/true-async/php-async.git
ARG ASYNC_BRANCH=main

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    autoconf bison build-essential curl re2c git \
    cmake ninja-build wget dos2unix pkg-config \
    libxml2-dev libssl-dev libargon2-dev \
    libcurl4-openssl-dev libedit-dev libreadline-dev \
    libsodium-dev libsqlite3-dev libonig-dev libzip-dev \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    libgmp-dev libpq-dev libbz2-dev libncurses-dev \
    libffi-dev libxslt1-dev libicu-dev libpsl-dev \
    && rm -rf /var/lib/apt/lists/*

# libuv 1.52.1
RUN wget -q https://github.com/libuv/libuv/archive/v1.52.1.tar.gz \
    && tar -xf v1.52.1.tar.gz \
    && cd libuv-1.52.1 && mkdir build && cd build \
    && cmake .. -G Ninja -DCMAKE_BUILD_TYPE=Release -DBUILD_TESTING=OFF \
    && ninja && ninja install && ldconfig \
    && cd / && rm -rf libuv*

RUN git clone --depth 1 --branch ${PHP_SRC_BRANCH} ${PHP_SRC_REPO} /usr/src/php-src \
    && git clone --depth 1 --branch ${ASYNC_BRANCH} ${ASYNC_REPO} /usr/src/php-src/ext/async

RUN dos2unix /usr/src/php-src/ext/async/config.m4

WORKDIR /usr/src/php-src

RUN ./buildconf --force \
    && ./configure \
        --prefix=/usr/local \
        --with-config-file-path=/etc \
        --with-config-file-scan-dir=/etc/php.d \
        --disable-debug \
        --enable-zts \
        --enable-async \
        --enable-embed \
        --enable-phpdbg \
        --enable-bcmath \
        --enable-calendar \
        --enable-exif \
        --enable-ftp \
        --enable-intl \
        --enable-mbstring \
        --enable-pdo \
        --enable-shmop \
        --enable-soap \
        --enable-sockets \
        --enable-pcntl \
        --enable-sysvmsg \
        --enable-sysvsem \
        --enable-sysvshm \
        --enable-gd \
        --enable-dba \
        --enable-xmlreader \
        --with-bz2 \
        --with-curl \
        --with-gettext \
        --with-gmp \
        --with-mysqli=mysqlnd \
        --with-openssl \
        --with-pdo-mysql=mysqlnd \
        --with-pdo-pgsql \
        --with-pdo-sqlite \
        --with-pgsql \
        --with-sqlite3 \
        --with-xsl \
        --with-zlib \
        --with-jpeg \
        --with-webp \
        --with-freetype \
        --with-zip \
        --with-sodium \
        --with-readline \
        --with-ffi \
        --without-pear \
    && make -j"$(nproc)" \
    && make install

# =============================================================================
# Stage 2: Build FrankenPHP binary
# =============================================================================
FROM ubuntu:24.04 AS go-builder

ARG FRANKENPHP_REPO=https://github.com/true-async/frankenphp.git
ARG FRANKENPHP_BRANCH=true-async

ENV DEBIAN_FRONTEND=noninteractive
ENV GOTOOLCHAIN=local
ENV PATH="/usr/local/go/bin:${PATH}"

COPY --from=golang:1.26 /usr/local/go /usr/local/go

RUN apt-get update && apt-get install -y \
    build-essential git pkg-config \
    libxml2-dev libssl-dev libargon2-dev \
    libcurl4-openssl-dev libedit-dev libreadline-dev libncurses-dev \
    libsodium-dev libsqlite3-dev libonig-dev libzip-dev \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    libgmp-dev libpq-dev libbz2-dev \
    libffi-dev libxslt1-dev libicu-dev libbrotli-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=php-builder /usr/local /usr/local
RUN ldconfig

RUN git clone --depth 1 --branch ${FRANKENPHP_BRANCH} ${FRANKENPHP_REPO} /usr/src/frankenphp

WORKDIR /usr/src/frankenphp
RUN go mod download

WORKDIR /usr/src/frankenphp/caddy
RUN go mod download

WORKDIR /usr/src/frankenphp/caddy/frankenphp
RUN sed -i 's/XtOffsetOf/offsetof/g' /usr/src/frankenphp/frankenphp_extension.c \
    && CGO_CFLAGS="$(php-config --includes)" \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    go build -tags "trueasync,nowatcher" -o /usr/local/bin/frankenphp .

# =============================================================================
# Stage 3: Runtime
# =============================================================================
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    libxml2 libssl3 libargon2-1 \
    libedit2 libreadline8 \
    libsodium23 libsqlite3-0 libonig5 libzip4 \
    libpng16-16 libjpeg8 libwebp7 libfreetype6 \
    libgmp10 libpq5 libbz2-1.0 \
    libffi8 libxslt1.1 libicu74 libpsl5 \
    ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=php-builder /usr/local /usr/local
COPY --from=go-builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

RUN ldconfig

COPY opcache.ini /etc/php.d/opcache.ini

ENV PATH="/usr/local/bin:/usr/local/sbin:${PATH}"

RUN mkdir -p /etc/php.d /etc/caddy /app

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

WORKDIR /app

EXPOSE 8080
