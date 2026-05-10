# syntax=docker/dockerfile:1.23@sha256:2780b5c3bab67f1f76c781860de469442999ed1a0d7992a5efdf2cffc0e3d769

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

FROM php:${PHP_VERSION}-apache AS php-base

RUN set -eux; \
  savedAptMark="$(apt-mark showmanual)"; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    ca-certificates \
    cron \
    curl \
    libbz2-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    zlib1g-dev; \
  docker-php-ext-configure gd --with-freetype --with-jpeg; \
  docker-php-ext-install -j"$(nproc)" bz2 gd intl pdo_mysql zip; \
  a2enmod rewrite; \
  apt-mark auto '.*' > /dev/null; \
  apt-mark manual \
    ${savedAptMark} \
    ca-certificates \
    cron \
    curl \
    unzip; \
  find /usr/local -type f \( -perm /0111 -o -name '*.so' \) -exec sh -c 'ldd "$@" 2>/dev/null' sh '{}' + \
    | awk 'NF == 4 && $2 == "=>" { print $3 } NF == 2 && $1 ~ /^\// { print $1 }' \
    | sort -u \
    | while read -r library; do \
        library="$(readlink -e "${library}")"; \
        dpkg-query --search "${library}" 2>/dev/null | grep -v '^diversion ' | head -n1 | cut -d: -f1; \
      done \
    | sort -u \
    | xargs -r apt-mark manual; \
  apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
  apt-get clean; \
  rm -rf /var/lib/apt/lists/* /var/cache/apt/*

FROM php-base AS composer-base

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  && apt-get install -y --no-install-recommends git \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /var/cache/apt/*

FROM composer-base AS php-vendor

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
  COMPOSER_CACHE_DIR=/tmp/composer-cache \
  composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction --no-progress

FROM composer-base AS php-dev-vendor
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
  COMPOSER_CACHE_DIR=/tmp/composer-cache \
  composer install --prefer-dist --optimize-autoloader --no-interaction --no-progress

FROM node:${NODE_VERSION}-bookworm-slim AS frontend-assets

WORKDIR /app

COPY package.json package-lock.json ./
COPY src/themes/admin_default/package.json src/themes/admin_default/package.json
COPY src/themes/huraga/package.json src/themes/huraga/package.json

RUN --mount=type=cache,target=/root/.npm npm ci

COPY src/themes/admin_default ./src/themes/admin_default
COPY src/themes/huraga ./src/themes/huraga
COPY src/modules ./src/modules

RUN NODE_ENV=production npm run build

FROM php-base AS release-tree

WORKDIR /app

ARG FOSSBILLING_VERSION=0.0.1
ARG FOSSBILLING_VERSION_TRUNCATE=0
ARG SENTRY_DSN=
ARG INSTALL_TRANSLATIONS=true
ARG TRANSLATIONS_URL=https://github.com/FOSSBilling/locale/releases/latest/download/translations.zip
ARG TRANSLATIONS_SHA256=

COPY src ./src
COPY README.md LICENSE ./src/
COPY --from=php-vendor /app/src/vendor ./src/vendor
COPY --from=frontend-assets /app/src/themes/admin_default/assets/build ./src/themes/admin_default/assets/build
COPY --from=frontend-assets /app/src/themes/huraga/assets/build ./src/themes/huraga/assets/build

RUN set -eux; \
  mkdir -p ./src/locale; \
  if [ "${INSTALL_TRANSLATIONS}" = "true" ]; then \
    curl -fsSL "${TRANSLATIONS_URL}" -o /tmp/translations.zip; \
    if [ -n "${TRANSLATIONS_SHA256}" ]; then \
      echo "${TRANSLATIONS_SHA256}  /tmp/translations.zip" | sha256sum -c -; \
    fi; \
    unzip -oq /tmp/translations.zip -d ./src/locale; \
    rm /tmp/translations.zip; \
  fi; \
  FOSSBILLING_VERSION="${FOSSBILLING_VERSION}" \
  FOSSBILLING_VERSION_TRUNCATE="${FOSSBILLING_VERSION_TRUNCATE}" \
  SENTRY_DSN="${SENTRY_DSN}" \
  php -r '$version = getenv("FOSSBILLING_VERSION") ?: "0.0.1"; $truncate = (int) (getenv("FOSSBILLING_VERSION_TRUNCATE") ?: 0); if ($truncate > 0) { $version = substr($version, 0, $truncate); } $versionFile = "./src/library/FOSSBilling/Version.php"; file_put_contents($versionFile, str_replace("0.0.1", $version, file_get_contents($versionFile))); $dsn = getenv("SENTRY_DSN"); if ($dsn !== false && $dsn !== "") { $sentryFile = "./src/library/FOSSBilling/SentryHelper.php"; file_put_contents($sentryFile, str_replace("--replace--this--during--release--process--", $dsn, file_get_contents($sentryFile))); }'; \
  chmod -R u=rwX,go=rX ./src

FROM scratch AS release-artifact

COPY --from=release-tree /app/src /

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

FROM runtime AS test

WORKDIR /workspace

COPY --from=release-tree /app/src ./src
COPY --from=php-dev-vendor /app/src/vendor ./src/vendor
COPY composer.json composer.lock phpstan.neon phpstan-baseline.neon phpunit.xml.dist phpunit-live.xml ./
COPY tests ./tests
COPY tests-legacy ./tests-legacy

RUN set -eux; \
  php -r '$config = require "./src/config-sample.php"; file_put_contents("./src/config.php", "<?php\nreturn " . var_export($config, true) . ";\n");'; \
  mkdir -p ./src/data/cache ./src/data/log ./src/data/uploads; \
  chown -R www-data:www-data ./src/data ./src/config.php
