FROM php:8.0-fpm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql