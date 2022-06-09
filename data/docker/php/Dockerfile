FROM php:8.0-fpm

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql

# Some more stuff we need
RUN apt-get install -y git zip unzip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer