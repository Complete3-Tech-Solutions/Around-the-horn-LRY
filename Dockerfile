# install PHP dependencies for development
FROM composer:2@sha256:41959f55087549989efcdfe953977b64e98e07ca0d7532d7e4b7fe1a90cc4159 AS composer_dev
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --prefer-dist

# install PHP dependencies for production
FROM composer:2@sha256:41959f55087549989efcdfe953977b64e98e07ca0d7532d7e4b7fe1a90cc4159 AS composer_prod
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --prefer-dist --no-dev

# install Node dependencies
FROM node:20@sha256:8f693eaa7e0a8e71560c9a82b55fd54c2ae920a2ba5d2cde28bac7d1c01c9ba5 AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install

# build assets
FROM dunglas/frankenphp:latest@sha256:932495e768c843729b043bfb0e40af6143b1bac98862d16aa93ea10d7338f8ed AS build
WORKDIR /app
COPY --from=composer_dev /app/vendor /app/vendor
COPY --from=node /app/node_modules /app/node_modules
COPY . /app/
RUN APP_ENV=prod php bin/console tailwind:build --minify
RUN APP_ENV=prod php bin/console importmap:install
RUN APP_ENV=prod php bin/console asset-map:compile

# build the final image — pinned to the exact digest verified by the load test
FROM dunglas/frankenphp:latest@sha256:932495e768c843729b043bfb0e40af6143b1bac98862d16aa93ea10d7338f8ed

ENV SERVER_NAME=your-app.com
ENV APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime
ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

COPY . /app/
COPY --from=composer_prod /app/vendor /app/vendor
COPY --from=node /app/node_modules /app/node_modules
COPY --from=build /app/public/assets /app/public/assets

# Ensure the SQLite database + event-state directory exists and is writable at
# runtime (the compose volume mounts here; FrankenPHP also needs var/ writable).
RUN mkdir -p /app/var/sqlite && chmod -R 777 /app/var
