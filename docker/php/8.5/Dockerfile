FROM phpswoole/swoole:6.2.5-php8.5

# Image gốc đã có sẵn: swoole, pdo, pdo_mysql, mysqlnd, ctype, fileinfo, sockets, curl, openssl, redis, mbstring...
# Chỉ cài thêm thư viện để build các extension còn thiếu + công cụ cần cho project.
RUN apt-get update && apt-get install -y --no-install-recommends \
    # gd (intervention/image, phpspreadsheet, flmngr)
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    # zip (phpspreadsheet)
    libzip-dev \
    # intl
    libicu-dev \
    # ffi
    libffi-dev \
    # gRPC: protoc + well-known types (google/protobuf/*.proto)
    protobuf-compiler \
    libprotobuf-dev \
    # composer cài package từ source
    git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
    bcmath \
    ffi \
    zip \
    gd \
    pcntl \
    intl

# (Tuỳ chọn) ext protobuf viết bằng C giúp encode/decode gRPC nhanh hơn google/protobuf thuần PHP.
# RUN pecl install protobuf && docker-php-ext-enable protobuf

ARG COMPOSER_VERSION
ENV COMPOSER_VERSION=$COMPOSER_VERSION

# Install composer
RUN curl "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" --output /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

WORKDIR /var/www/html
