# Dockerfile

# 1) Base image: PHP 8.4 FPM
FROM php:8.4-fpm

# 2) Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    # Install Nginx and Supervisor
    nginx \
    supervisor \
    # Install Postgres dependencies
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# 3) Install PHP extensions (including pdo_pgsql)
RUN docker-php-ext-install pdo pdo_pgsql

# 4) Set the working directory
WORKDIR /var/www

# 5) Copy Laravel project files to /var/www
COPY . /var/www

# 6) Create necessary directories and set permissions
RUN mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 7) Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev

# 8) Copy Nginx config
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# 9) Copy Supervisor config
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 10) Expose port 80 for Nginx
EXPOSE 80

# 11) Start Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]