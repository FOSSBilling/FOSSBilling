FROM php:8.0-fpm
FROM composer:2 as composer

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql

# Some more stuff we need
RUN apt-get install -y --no-install-recommends git zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer