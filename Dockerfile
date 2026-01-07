FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy startup script FIRST (before application files)
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Apache will listen on this port (Railway will inject PORT env var)
EXPOSE 8080

# Start using our custom script
CMD ["/start.sh"]
