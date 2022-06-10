FROM php:8.0-fpm

RUN apt-get update \
    && docker-php-ext-install \
        pdo_mysql

# Cron jobs
RUN apt-get install -y cron
COPY fossbilling-crontab /etc/cron.d/fossbilling-crontab
RUN chmod 0644 /etc/cron.d/fossbilling-crontab && \
    crontab /etc/cron.d/fossbilling-crontab