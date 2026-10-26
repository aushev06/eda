# Production image: FrankenPHP serves /app/public; the same image runs the
# queue worker, Reverb and the scheduler (see deploy/docker/compose.yaml).
FROM dunglas/frankenphp:1-php8.5 AS base

RUN install-php-extensions pdo_mysql intl zip gd bcmath pcntl exif

WORKDIR /app

FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

# Baked into the JS bundle at build time.
ARG VITE_APP_NAME
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev \
    && php artisan storage:link \
    && npm run build \
    && rm -rf node_modules

FROM base

COPY --from=build /app /app

ENV SERVER_NAME=:80

CMD ["sh", "-c", "php artisan optimize && php artisan filament:optimize && exec frankenphp run --config /etc/frankenphp/Caddyfile"]
