# ─────────────────────────────────────────────────────────────
# Stage 1: Build PHP dependencies
# ─────────────────────────────────────────────────────────────
FROM php:8.3-cli-alpine AS vendor

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    $PHPIZE_DEPS \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    openssl-dev \
    postgresql-dev

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp && \
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
    fileinfo && \
    pecl install redis && \
    docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency manifests first for better Docker layer caching
COPY composer.json composer.lock ./

# Composer settings
#
# GitHub codeload has previously returned HTTP/2 400 from this host.
# Force Git HTTP/1.1 and use source installs instead of dist ZIP files.
ENV COMPOSER_PROCESS_TIMEOUT=600
ENV COMPOSER_MAX_PARALLEL_HTTP=1

RUN git config --global http.version HTTP/1.1 && \
    composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-source \
    --optimize-autoloader \
    --no-scripts


# ─────────────────────────────────────────────────────────────
# Stage 2: Production image
# ─────────────────────────────────────────────────────────────
FROM php:8.3-cli-alpine AS production

# Runtime dependencies only
RUN apk add --no-cache \
    curl \
    libpng \
    libjpeg-turbo \
    libwebp \
    freetype \
    libzip \
    icu-libs \
    oniguruma \
    libxml2 \
    postgresql-libs

# Copy compiled PHP extensions and extension configs from builder
COPY --from=vendor /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=vendor /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# OPcache tuning
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

WORKDIR /var/www/html

# Copy Laravel source
COPY . .

# Copy production Composer dependencies
COPY --from=vendor /app/vendor ./vendor

# Ensure Laravel runtime directories exist
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy container entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]