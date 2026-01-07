FROM php:8.2-cli

RUN apt-get update && apt-get install -y default-mysql-client && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /app
COPY . /app

EXPOSE 8080

# Create startup script
RUN echo '#!/bin/bash\nPORT=${PORT:-8080}\nphp -S 0.0.0.0:$PORT -t .' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]
