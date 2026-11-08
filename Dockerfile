# Laravel 12 PHP 8.2 Production Container
FROM php:8.2-cli-alpine

WORKDIR /var/www/html

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    sqlite-libs \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql pcntl bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Set permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:3000/health || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3000"]
