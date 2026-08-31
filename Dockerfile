# ── Stage 1: PHP dependencies ────────────────────────────────────────────────
FROM php:8.3-cli-alpine AS vendor

# Install system packages required to build PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    openssl-dev \
    postgresql-dev

# Install PHP extensions
# - pdo_pgsql  : PostgreSQL (required, DB_CONNECTION=pgsql)
# - gd         : dompdf PDF rendering + intervention/image
# - zip        : dompdf + Laravel
# - mbstring   : Laravel core + dompdf
# - xml / dom  : dompdf HTML parsing
# - intl       : Laravel internationalisation
# - bcmath     : Laravel encryption helpers
# - opcache    : Production opcode cache
# - openssl    : firebase/php-jwt JWKS verification
# - fileinfo   : Laravel file uploads
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        gd \
        zip \
        mbstring \
        xml \
        dom \
        intl \
        bcmath \
        opcache \
        fileinfo

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only dependency manifests first — layer-cache friendly
COPY composer.json composer.lock ./

# Install production dependencies only
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ── Stage 2: Production image ─────────────────────────────────────────────────
FROM php:8.3-cli-alpine

# Runtime system packages
RUN apk add --no-cache \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    icu-libs \
    oniguruma \
    libxml2 \
    postgresql-libs

# Copy compiled extensions from build stage
COPY --from=vendor /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=vendor /usr/local/etc/php/conf.d    /usr/local/etc/php/conf.d

# OPcache tuning for production CLI server
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

WORKDIR /var/www/html

# Copy application source
COPY . .

# Copy vendor from build stage
COPY --from=vendor /app/vendor ./vendor

# Ensure Laravel runtime directories exist and are writable
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy and set up entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
