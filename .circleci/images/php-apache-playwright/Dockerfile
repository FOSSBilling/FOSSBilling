# cspell:words cimg
FROM docker.io/fossbilling/circleci-php-apache:php8.5.10-node-apache-25434d4@sha256:acfccef40b6fdd3f274e0bad1c28661e2393ee48beefd62f6ebe3181804033aa

LABEL org.opencontainers.image.source="https://github.com/FOSSBilling/FOSSBilling" \
    org.opencontainers.image.description="CircleCI PHP integration executor with Apache and Chromium preinstalled"

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
USER root
COPY .circleci/playwright/package.json .circleci/playwright/package-lock.json /tmp/playwright-runner/
RUN cd /tmp/playwright-runner \
    && npm ci --no-audit --no-fund --workspaces=false \
    && ./node_modules/.bin/playwright install --with-deps --only-shell chromium \
    && chmod -R a+rX /ms-playwright \
    && rm -rf /tmp/playwright-runner /root/.npm /var/lib/apt/lists/*
USER circleci
