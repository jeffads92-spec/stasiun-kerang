FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application files
WORKDIR /app
COPY . /app

# Expose port
EXPOSE 80

# Use PHP built-in server with Railway PORT variable
CMD php -S 0.0.0.0:${PORT:-80} -t .
