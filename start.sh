#!/bin/bash
set -e

# Get PORT from Railway or default to 8080
PORT=${PORT:-8080}

echo "===================================="
echo "Starting Apache Configuration"
echo "Port: $PORT"
echo "===================================="

# Fix MPM conflict at runtime
echo "Fixing MPM modules..."
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
if [ ! -L /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
    ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi

# Use template files to avoid repeated sed replacements
if [ ! -f /etc/apache2/ports.conf.original ]; then
    cp /etc/apache2/ports.conf /etc/apache2/ports.conf.original
    cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf.original
fi

# Always start from original template
cp /etc/apache2/ports.conf.original /etc/apache2/ports.conf
cp /etc/apache2/sites-available/000-default.conf.original /etc/apache2/sites-available/000-default.conf

# Update Apache ports.conf
echo "Updating /etc/apache2/ports.conf..."
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Update VirtualHost in default site config
echo "Updating /etc/apache2/sites-available/000-default.conf..."
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Verify changes
echo "===================================="
echo "Active MPM modules:"
ls -la /etc/apache2/mods-enabled/mpm_* 2>/dev/null || echo "No MPM modules found"
echo ""
echo "Apache Configuration:"
grep -E "^Listen" /etc/apache2/ports.conf
grep "VirtualHost" /etc/apache2/sites-available/000-default.conf | head -1
echo "===================================="

echo "Starting Apache on port $PORT..."

# Start Apache in foreground
exec apache2-foreground
