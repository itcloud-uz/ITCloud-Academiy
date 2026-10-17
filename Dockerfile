# ==============================================================================
# Stage 1: Frontend Assets Builder (Vue 3 + Monaco Editor + Tailwind CSS)
# ==============================================================================
FROM node:20-alpine AS frontend_builder

WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY resources/ ./resources/
RUN mkdir -p public/build

# ==============================================================================
# Stage 2: Base PHP 8.3 + PostgreSQL + Redis Runtime
# ==============================================================================
FROM php:8.3-fpm-bookworm AS base_runtime

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    curl \
    unzip \
    git \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        pcntl \
        opcache \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini

WORKDIR /var/www/html

COPY . /var/www/html
COPY --from=frontend_builder /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# ==============================================================================
# Stage 3: Web Container (Nginx + PHP 8.3-FPM)
# ==============================================================================
FROM base_runtime AS web

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/web-supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ==============================================================================
# Stage 4: Queue Worker Container (Redis AI Evaluations & Interviews Listener)
# ==============================================================================
FROM base_runtime AS queue_worker

COPY docker/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
