FROM php:8.1-apache

RUN a2enmod rewrite headers

RUN docker-php-ext-install pdo pdo_mysql

ADD . /var/www/html