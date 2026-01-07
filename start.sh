#!/bin/bash
set -e

# Get PORT from environment or use default
PORT=${PORT:-8080}

echo "Configuring Apache to listen on port $PORT"

# Update Apache ports configuration
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Update VirtualHost configuration
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port $PORT"

# Start Apache
exec apache2-foreground
