# cspell:words cimg
FROM cimg/php:8.5.10-node@sha256:c60f1384d1eda0bd1a57c60e23a55c4a87b8fcd59cc2ea89c6d1c687d9d64fca AS apache

LABEL org.opencontainers.image.source="https://github.com/FOSSBilling/FOSSBilling" \
    org.opencontainers.image.description="CircleCI PHP integration executor with Apache preinstalled"

USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 \
    && rm -rf /var/lib/apt/lists/*
USER circleci

FROM apache AS browser

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
