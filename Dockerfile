# Use the default Laravel Sail PHP 8.2 base image
FROM ghcr.io/laravelphp/sail:2.0/php82

# Install APCu
RUN apt-get update && \
    apt-get install -y \
    libpcre3-dev \
    && pecl install apcu \
    && docker-php-ext-enable apcu
