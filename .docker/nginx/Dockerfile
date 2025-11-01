# ================================
# NGINX Production Image
# ================================

FROM nginx:1.25-alpine

# Add metadata
LABEL maintainer="Tripwire Team" \
      version="1.0" \
      description="Tripwire NGINX Container"

# Install security updates and basic tools
RUN apk update && apk upgrade && \
    apk add --no-cache \
    curl \
    certbot \
    certbot-nginx \
    && rm -rf /var/cache/apk/*

# Create non-root user
RUN addgroup -g 1000 -S nginxuser && \
    adduser -u 1000 -S nginxuser -G nginxuser

# Create necessary directories
RUN mkdir -p /var/cache/nginx /var/log/nginx /etc/nginx/ssl && \
    chown -R nginxuser:nginxuser /var/cache/nginx /var/log/nginx /etc/nginx/ssl

# Copy nginx configuration
COPY ./.docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./.docker/nginx/tripwire.conf /etc/nginx/conf.d/default.conf

# Copy SSL configuration (optional)
# COPY ./.docker/nginx/ssl/ /etc/nginx/ssl/

# Security hardening
RUN touch /var/run/nginx.pid && \
    chown nginxuser:nginxuser /var/run/nginx.pid

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Switch to non-root user
USER nginxuser

# Expose ports
EXPOSE 80 443

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
