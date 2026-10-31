FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-fpm-bookworm AS app
WORKDIR /var/www
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl git libicu-dev libonig-dev libpng-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install bcmath gd intl mbstring opcache pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=frontend /build/public/build ./public/build
RUN composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/public
COPY --from=frontend /build/public/build /var/www/public/build
EXPOSE 80
