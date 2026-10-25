# ==============================================================================
# Laravel 11 backend image — PHP 8.3 with healthcare-friendly extensions
# ==============================================================================
FROM php:8.3-cli-alpine AS base

# System packages + PHP extensions required by Laravel and our stack
RUN apk add --no-cache \
        bash \
        git \
        curl \
        zip \
        unzip \
        autoconf \
        g++ \
        make \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        postgresql-dev \
        libzip-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        sockets \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer (pinned)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist || true

COPY . .
RUN composer dump-autoload --optimize || true

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
