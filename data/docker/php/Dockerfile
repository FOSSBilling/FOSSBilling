FROM php:8.0-fpm

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql \
    && docker-php-ext-enable \
        pdo_mysql \
    && docker-php-ext-install \
        gettext \
    && docker-php-ext-enable \
        gettext \
    && docker-php-ext-install \
        zip \
    && docker-php-ext-enable \
        zip \

# Some more stuff we need
RUN apt-get install -y git zip unzip 7z

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composerd