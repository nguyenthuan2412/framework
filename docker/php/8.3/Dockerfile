FROM phpswoole/swoole:5.1.8-php8.3

RUN apt-get update && apt-get install -y \
    libffi-dev \
    zlib1g-dev \
    libbrotli-dev \
    libnss3-tools \
    libnghttp2-dev \
    libmariadb-dev-compat \
    libmariadb-dev \
    libjson-c-dev \
    build-essential \
    libcurl4-openssl-dev \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    git \
    libicu-dev \
    libmagickwand-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp

# Install extensions php
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    bcmath \
    ctype \
    fileinfo \
    mysqli \
    sockets \
    ffi \
    zip \
    gd \
    pcntl \
    intl

RUN docker-php-ext-enable \
    pdo \
    pdo_mysql \
    bcmath \
    ctype \
    fileinfo \
    mysqli \
    sockets \
    ffi \
    zip \
    gd \
    pcntl \
    intl

RUN pecl install imagick \
    && docker-php-ext-enable imagick

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

ARG COMPOSER_VERSION
ENV COMPOSER_VERSION=$COMPOSER_VERSION

# Install composer
RUN curl "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" --output /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

WORKDIR /var/www/html