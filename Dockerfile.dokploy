# ============================================
# Stage 0: Runtime base + PHP extensions
# Keep in sync with Dockerfile.dokploy (Dokploy entry at repo root).
# ============================================
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    mysql-client \
    netcat-openbsd \
    nginx \
    oniguruma-dev \
    supervisor \
    unzip \
    zip \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ============================================
# Stage 1: Composer deps (cached when lock unchanged)
# ============================================
FROM base AS deps

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ============================================
# Stage 2: Production runtime
# ============================================
FROM base AS runtime

COPY --from=deps /var/www/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p \
      bootstrap/cache \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/supervisor.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

VOLUME ["/var/www/storage/app", "/var/www/storage/logs", "/var/www/storage/framework"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
