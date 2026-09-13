FROM php:8.0-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && find /var/www/html/pages/treasury/uploads -type d -exec chmod 775 {} \; 2>/dev/null || true

# Allow .htaccess overrides
RUN printf '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-enabled/civentrel.conf

EXPOSE 80
