FROM php:8.0-fpm

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql

# Some more stuff we need
RUN apt-get install -y --no-install-recommends git zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer