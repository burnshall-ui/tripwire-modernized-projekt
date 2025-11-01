# ================================
# WebSocket Server Image
# ================================

FROM php:8.2-cli-alpine3.19

# Add metadata
LABEL maintainer="Tripwire Team" \
      version="1.0" \
      description="Tripwire WebSocket Server"

# Install system dependencies
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    pkgconfig \
    libevent-dev \
    && rm -rf /var/cache/apk/*

# Install PHP extensions required for WebSocket
RUN docker-php-ext-install \
    pdo_mysql \
    pcntl \
    && pecl install event \
    && docker-php-ext-enable event

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create non-root user
RUN addgroup -g 1000 -S wsuser && \
    adduser -u 1000 -S wsuser -G wsuser -h /opt/websocket

# Create necessary directories
RUN mkdir -p /opt/websocket /var/log/websocket && \
    chown -R wsuser:wsuser /opt/websocket /var/log/websocket

# Set working directory
WORKDIR /opt/websocket

# Copy composer files first for better caching
COPY websockets/composer.json websockets/composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application code
COPY websockets/ ./

# Copy configuration files
COPY config.php db.inc.php ./

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD php -r "echo 'OK';" || exit 1

# Switch to non-root user
USER wsuser

# Expose WebSocket port
EXPOSE 8080

# Start WebSocket server
CMD ["php", "WebSocketServer.php"]
