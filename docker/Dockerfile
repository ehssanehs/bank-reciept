# ------------------------------------------------------------------
# Bank Payment Verification — application image (php-fpm + tesseract)
# ------------------------------------------------------------------
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        tesseract-ocr \
        tesseract-ocr-data-fas \
        tesseract-ocr-data-eng \
        icu-dev \
        libzip-dev \
        zip \
        unzip \
        curl \
        git \
        oniguruma-dev \
        libxml2-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        imagemagick \
        imagemagick-dev \
        libwebp-dev

# --- PHP extensions ---
RUN docker-php-ext-configure intl && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install \
        pdo pdo_mysql mbstring tokenizer xml ctype json \
        bcmath intl gd zip opcache exif && \
    pecl install redis && docker-php-ext-enable redis

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy the application
COPY backend/ /var/www/html/

# Install production dependencies (resolves + generates a lock if needed)
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction || \
    composer install --no-dev --prefer-dist --no-interaction
RUN php artisan package:discover --ansi || true

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache public && \
    mkdir -p storage/logs bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/bankpay.ini
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord/supervisord.conf /etc/supervisord.conf

EXPOSE 9000 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
