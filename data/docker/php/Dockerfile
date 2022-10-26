FROM php:8.0-fpm

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql \
        intl