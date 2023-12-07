FROM php:8.2-apache

# Install required packages, configure Apache, install PHP exensions, and clean-up.
RUN apt-get update \
  && apt-get install -y --no-install-recommends wget unzip zlib1g-dev libpng-dev libicu-dev libbz2-dev libmagickwand-dev \
  && a2enmod rewrite \
  && docker-php-ext-configure bz2 \
  && docker-php-ext-install -j$(nproc) bz2 \
  && docker-php-ext-configure gd \
  && docker-php-ext-install -j$(nproc) gd \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl \
  && docker-php-ext-configure opcache \
  && docker-php-ext-install -j$(nproc) opcache \
  && docker-php-ext-configure pdo_mysql \
  && docker-php-ext-install -j$(nproc) pdo_mysql \
  && pecl install imagick \
  && docker-php-ext-enable imagick \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# Copy files and set required permissions.
COPY --chown=www-data:www-data ./src/. /var/www/html
