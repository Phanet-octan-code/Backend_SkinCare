# ========================================================
# Production Dockerfile for Laravel 12 on Render (render.com)
# Multi-process: Nginx + PHP 8.2 FPM via Supervisord
# Database: PostgreSQL (pdo_pgsql)
# ========================================================
FROM php:8.2-fpm-alpine

# Set non-interactive environment
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies, Nginx, Supervisor, and Postgres dev libraries
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    libpq-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-client

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        gd \
        intl \
        zip \
        bcmath \
        opcache

# Recommended production PHP configuration
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "upload_max_filesize=25M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=25M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Configure PHP-FPM to preserve environment variables and log worker output
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "decorate_workers_output = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Install Composer 2 from official image
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first to leverage Docker layer caching
COPY composer.json composer.lock ./

# Install dependencies (no dev, optimized)
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy application source code
COPY . .

# Set up storage and cache directories with proper permissions
RUN mkdir -p /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/storage/app/public \
             /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy Nginx, Supervisor, and Entrypoint configurations
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose HTTP port (Render routes dynamic PORT or port 80/10000)
EXPOSE 80

# Run entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

