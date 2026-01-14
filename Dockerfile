FROM php:8.5-apache@sha256:96b50b7861cb6d1c10cd9f58c46b2c19606761a1209c20d410a509d4d1c935fd

# Install required packages, configure Apache, install PHP extensions, and clean-up.
RUN apt-get update \
  && apt-get install -y --no-install-recommends zlib1g-dev libpng-dev libicu-dev libbz2-dev cron \
  && a2enmod rewrite \
  && docker-php-ext-configure bz2 \
  && docker-php-ext-install -j$(nproc) bz2 \
  && docker-php-ext-configure gd \
  && docker-php-ext-install -j$(nproc) gd \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl \
  && docker-php-ext-configure pdo_mysql \
  && docker-php-ext-install -j$(nproc) pdo_mysql \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /var/cache/apt/*

# Copy files and set required permissions.
COPY --chown=www-data:www-data ./src/. /var/www/html

RUN { crontab -l -u www-data 2>/dev/null; echo "*/5 * * * * /usr/local/bin/php /var/www/html/cron.php"; } | crontab -u www-data -

# Start cron and then run Apache in the foreground when the container starts.
CMD service cron start && apache2-foreground
