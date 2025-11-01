# ================================
# PHP-FPM Multi-stage Build
# ================================

# Base stage for dependencies
FROM alpine:3.19 AS base

# Install build dependencies
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    pkgconfig \
    && rm -rf /var/cache/apk/*

# ================================
# PHP-FPM Production Image
# ================================

FROM php:8.2-fpm-alpine3.19

# Add metadata
LABEL maintainer="Tripwire Team" \
      version="1.0" \
      description="Tripwire PHP-FPM Container"

# Install system dependencies
RUN apk add --no-cache \
    # Core dependencies
    nginx \
    supervisor \
    # PHP extensions dependencies
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    # Database
    mysql-client \
    # Utils
    curl \
    git \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        bcmath \
        dba \
        gd \
        intl \
        mbstring \
        soap \
        xml \
        zip \
        opcache

# Install PECL extensions
RUN pecl install apcu imagick redis \
    && docker-php-ext-enable apcu imagick redis

# Create non-root user
RUN addgroup -g 1000 -S appuser \
    && adduser -u 1000 -S appuser -G appuser -h /opt/app

# Create necessary directories
RUN mkdir -p /var/log/php-fpm /var/run/php-fpm /opt/app \
    && chown -R appuser:appuser /var/log/php-fpm /var/run/php-fpm /opt/app

# Copy PHP configuration
COPY ./.docker/php-fpm/php.ini /usr/local/etc/php/php.ini
COPY ./.docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./.docker/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.conf

# Copy supervisor configuration
COPY ./.docker/supervisor/supervisord.conf /etc/supervisord.conf

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php-fpm -t || exit 1

# Switch to non-root user
USER appuser

# Set working directory
WORKDIR /opt/app

# Expose port
EXPOSE 9000

# Start supervisor (manages php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
