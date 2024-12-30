# Dockerfile

# 1) Base image: PHP 8.4 FPM
FROM php:8.4-fpm

# 2) Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    # sqlite command-line tool is optional if you want to query inside the container
    sqlite3 \
    libsqlite3-dev

# 3) Install PHP extensions (including pdo_sqlite)
RUN docker-php-ext-install pdo pdo_sqlite

# 4) Set the working directory
WORKDIR /var/www

# 5) Copy Laravel project files to /var/www
COPY . /var/www

# 6) Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev

# 7) Fix permissions for storage & bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 8) Expose port for php-fpm (internal use only)
EXPOSE 9000

# 9) Default command: start php-fpm
CMD ["php-fpm"]