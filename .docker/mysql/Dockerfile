# ================================
# MySQL 8.0 Production Image
# ================================

FROM mysql:8.0-debian

# Add metadata
LABEL maintainer="Tripwire Team" \
      version="1.0" \
      description="Tripwire MySQL Container"

# Environment variables for MySQL
ENV MYSQL_ROOT_PASSWORD=rootpassword
ENV MYSQL_DATABASE=tripwire
ENV MYSQL_USER=tripwire
ENV MYSQL_PASSWORD=tripwirepass

# Copy custom MySQL configuration
COPY ./.docker/mysql/my.cnf /etc/mysql/conf.d/tripwire.cnf
COPY ./.docker/mysql/setup.sql /docker-entrypoint-initdb.d/01-tripwire.sql

# Create necessary directories with proper permissions
RUN mkdir -p /var/lib/mysql-files /var/lib/mysql-keyring && \
    chown -R mysql:mysql /var/lib/mysql-files /var/lib/mysql-keyring

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD mysqladmin ping -h localhost -u root -p${MYSQL_ROOT_PASSWORD} || exit 1

# Expose port
EXPOSE 3306

# Use the default MySQL entrypoint
CMD ["mysqld"]
