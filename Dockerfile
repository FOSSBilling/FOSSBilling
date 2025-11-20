FROM php:8.4-apache@sha256:2e8ef5f8a353d88fd93fa8b1ec6549511701bed888c6d2cb20d5c774f9dae4ee

# Install required packages, configure Apache, install PHP extensions, and clean-up.
RUN apt-get update \
  && apt-get install -y --no-install-recommends wget unzip zlib1g-dev libpng-dev libicu-dev libbz2-dev cron \
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
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# Copy files and set required permissions.
COPY --chown=www-data:www-data ./src/. /var/www/html

RUN { crontab -l -u www-data 2>/dev/null; echo "*/5 * * * * /usr/local/bin/php /var/www/html/cron.php"; } | crontab -u www-data -
