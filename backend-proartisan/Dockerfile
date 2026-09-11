# ==============================================================================
# Dockerfile Production — ProsArtisan Backend (Laravel 11 / PHP 8.3 FPM)
# ==============================================================================

# --- Stage 1 : Build des assets Frontend (React / Inertia / Vite) ---
FROM node:20-alpine AS node-builder

WORKDIR /app
COPY package*.json ./
RUN npm ci --prefer-offline --no-audit

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.ts tsconfig.json ./
RUN npm run build

# --- Stage 2 : Image PHP-FPM Production ---
FROM php:8.3-fpm-bookworm

# Variables d'environnement
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dépendances système et extensions PHP requises
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    supervisor \
    wkhtmltopdf \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configuration PHP Opcache de production
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.enable_cli=1'; \
    echo 'upload_max_filesize=30M'; \
    echo 'post_max_size=35M'; \
    echo 'memory_limit=512M'; \
} > /usr/local/etc/php/conf.d/custom-production.ini

WORKDIR /var/www/backend-proartisan

# Copier le code backend et les dépendances Composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

# Rapatrier les assets compilés depuis le builder Node
COPY --from=node-builder /app/public/build ./public/build

# Droits et permissions
RUN chown -R www-data:www-data /var/www/backend-proartisan/storage /var/www/backend-proartisan/bootstrap/cache \
    && chmod -R 775 /var/www/backend-proartisan/storage /var/www/backend-proartisan/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
