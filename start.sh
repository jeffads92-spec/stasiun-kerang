#!/bin/bash
set -e

# Get PORT from Railway or default to 8080
PORT=${PORT:-8080}

echo "===================================="
echo "Starting Apache Configuration"
echo "Port: $PORT"
echo "===================================="

# Update Apache ports.conf
echo "Updating /etc/apache2/ports.conf..."
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Update VirtualHost in default site config
echo "Updating /etc/apache2/sites-available/000-default.conf..."
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Verify changes
echo "===================================="
echo "Apache Configuration:"
cat /etc/apache2/ports.conf | grep Listen
cat /etc/apache2/sites-available/000-default.conf | grep VirtualHost
echo "===================================="

echo "Starting Apache on port $PORT..."

# Start Apache in foreground
exec apache2-foreground
