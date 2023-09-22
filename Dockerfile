FROM php:8.1-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/fossbilling/src
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN rm -f /var/lib/apt/lists/* ||true
RUN apt update -y
RUN apt install git unzip libzip-dev -y

RUN a2enmod rewrite headers
RUN docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY --from=node:20-slim /usr/local/bin /usr/local/bin
COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

ADD . /var/www/fossbilling