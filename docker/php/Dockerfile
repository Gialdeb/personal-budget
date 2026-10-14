FROM php:8.5-fpm AS runtime

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libfcgi-bin \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl bcmath pcntl zip gd exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM runtime AS dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:22-bookworm-slim AS node-binary

FROM dependencies AS frontend
COPY --from=node-binary /usr/local/bin/node /usr/local/bin/node
COPY --from=node-binary /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
ARG VITE_APP_NAME="Soamco Budget"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array HUSKY=0
RUN npm ci --include=dev && npm run build && test -s public/build/manifest.json

FROM runtime AS production
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
COPY --chmod=755 docker/php/production-entrypoint /usr/local/bin/production-entrypoint
COPY --chmod=755 docker/php/healthcheck /usr/local/bin/application-healthcheck
RUN test -f artisan && test -f vendor/autoload.php && test -s public/build/manifest.json \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chmod -R ug+rwX storage bootstrap/cache
USER www-data
ENTRYPOINT ["production-entrypoint"]
CMD ["php-fpm"]
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=12 CMD ["application-healthcheck"]

FROM runtime AS development
