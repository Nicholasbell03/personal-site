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

# 3) Remove default Nginx configuration
RUN rm -rf /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default

# 4) Install PHP extensions (including pdo_pgsql)
RUN docker-php-ext-install pdo pdo_pgsql

# 5) Set the working directory
WORKDIR /var/www

# 6) Copy Laravel project files to /var/www
COPY . /var/www

# 7) Create necessary directories and set permissions
RUN mkdir -p /var/www/storage/logs \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 8) Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev

# 9) Copy Nginx config
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# 10) Copy Supervisor config
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure PHP and PHP-FPM
RUN { \
    echo 'display_errors = On'; \
    echo 'display_startup_errors = On'; \
    echo 'error_reporting = E_ALL'; \
    echo 'log_errors = On'; \
    echo 'error_log = /dev/stderr'; \
} > /usr/local/etc/php/conf.d/error-logging.ini

# Configure PHP-FPM
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 10'; \
    echo 'pm.start_servers = 2'; \
    echo 'pm.min_spare_servers = 1'; \
    echo 'pm.max_spare_servers = 3'; \
    echo 'catch_workers_output = yes'; \
    echo 'decorate_workers_output = no'; \
    echo 'php_admin_flag[log_errors] = on'; \
    echo 'php_admin_value[error_log] = /dev/stderr'; \
} > /usr/local/etc/php-fpm.d/www.conf

# 11) Expose port 80 for Nginx
EXPOSE 80

# 12) Start Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
