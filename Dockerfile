FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Fix MPM conflict - remove all MPM modules and enable only prefork
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
RUN a2enmod mpm_prefork

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Backup original Apache configs
RUN cp /etc/apache2/ports.conf /etc/apache2/ports.conf.original && \
    cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf.original

# Copy startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 8080

CMD ["/start.sh"]
