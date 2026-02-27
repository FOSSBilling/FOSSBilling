FROM php:8.5-apache@sha256:9be84c47f2791d429a3fd82beee8109be123feb093e6c428269aa311ef1d3190

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

# Configure cron job for www-data in a dedicated crontab file for clarity.
RUN echo '*/5 * * * * /usr/local/bin/php /var/www/html/cron.php' > /tmp/www-data.cron \
  && crontab -u www-data /tmp/www-data.cron \
  && rm /tmp/www-data.cron

# Start cron and then run Apache in the foreground when the container starts.
CMD ["sh", "-c", "cron && exec apache2-foreground"]
