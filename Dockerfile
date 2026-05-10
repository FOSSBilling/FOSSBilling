# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

FROM php:${PHP_VERSION}-apache AS php-base

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    ca-certificates \
    cron \
    curl \
    git \
    libbz2-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    zlib1g-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" bz2 gd intl pdo_mysql zip \
  && a2enmod rewrite \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /var/cache/apt/*

FROM php-base AS php-vendor

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
  COMPOSER_CACHE_DIR=/tmp/composer-cache \
  composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction --no-progress

FROM node:${NODE_VERSION}-bookworm-slim AS frontend-assets

WORKDIR /app

COPY package.json package-lock.json ./
COPY src/themes/admin_default/package.json src/themes/admin_default/package.json
COPY src/themes/huraga/package.json src/themes/huraga/package.json

RUN --mount=type=cache,target=/root/.npm npm ci

COPY src ./src

RUN NODE_ENV=production npm run build

FROM php-base AS release-tree

WORKDIR /app

ARG FOSSBILLING_VERSION=0.0.1
ARG FOSSBILLING_VERSION_TRUNCATE=0
ARG SENTRY_DSN=
ARG TRANSLATIONS_URL=https://github.com/FOSSBilling/locale/releases/latest/download/translations.zip

COPY src ./src
COPY README.md LICENSE ./src/
COPY --from=php-vendor /app/src/vendor ./src/vendor
COPY --from=frontend-assets /app/src/themes/admin_default/assets/build ./src/themes/admin_default/assets/build
COPY --from=frontend-assets /app/src/themes/huraga/assets/build ./src/themes/huraga/assets/build

RUN set -eux; \
  mkdir -p ./src/locale; \
  curl -fsSL "${TRANSLATIONS_URL}" -o /tmp/translations.zip; \
  unzip -oq /tmp/translations.zip -d ./src/locale; \
  rm /tmp/translations.zip; \
  FOSSBILLING_VERSION="${FOSSBILLING_VERSION}" \
  FOSSBILLING_VERSION_TRUNCATE="${FOSSBILLING_VERSION_TRUNCATE}" \
  SENTRY_DSN="${SENTRY_DSN}" \
  php -r '$version = getenv("FOSSBILLING_VERSION") ?: "0.0.1"; $truncate = (int) (getenv("FOSSBILLING_VERSION_TRUNCATE") ?: 0); if ($truncate > 0) { $version = substr($version, 0, $truncate); } $versionFile = "./src/library/FOSSBilling/Version.php"; file_put_contents($versionFile, str_replace("0.0.1", $version, file_get_contents($versionFile))); $dsn = getenv("SENTRY_DSN"); if ($dsn !== false && $dsn !== "") { $sentryFile = "./src/library/FOSSBilling/SentryHelper.php"; file_put_contents($sentryFile, str_replace("--replace--this--during--release--process--", $dsn, file_get_contents($sentryFile))); }'; \
  chmod -R u=rwX,go=rX ./src

FROM php-base AS runtime

WORKDIR /var/www/html

COPY --from=release-tree --chown=www-data:www-data /app/src/ ./

RUN set -eux; \
  mkdir -p data/cache data/log data/uploads; \
  touch config.php /var/log/cron.log; \
  chown -R www-data:www-data data config.php /var/log/cron.log; \
  echo '*/5 * * * * /usr/local/bin/php /var/www/html/cron.php >> /var/log/cron.log 2>&1' > /tmp/www-data.cron; \
  crontab -u www-data /tmp/www-data.cron; \
  rm /tmp/www-data.cron

CMD ["sh", "-c", "cron & exec apache2-foreground"]
