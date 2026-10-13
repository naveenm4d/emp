# syntax=docker/dockerfile:1
# Production image for Render: nginx + php-fpm + queue worker + scheduler,
# run by supervisor (see docker/render).

FROM php:8.5-fpm-trixie AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor gettext-base unzip \
    && install-php-extensions pdo_pgsql intl gd zip bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Build: PHP dependencies, then the Vite bundle. The Wayfinder Vite plugin
# runs `php artisan wayfinder:generate`, so PHP and Node share this stage.
# ---------------------------------------------------------------------------
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules public/hot

# ---------------------------------------------------------------------------
# Runtime
# ---------------------------------------------------------------------------
FROM base AS app

COPY docker/render/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/render/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/render/nginx.conf.template /etc/nginx/app.conf.template
COPY docker/render/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/render/start.sh /usr/local/bin/start

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

EXPOSE 10000

CMD ["start"]
