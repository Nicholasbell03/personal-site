FROM serversideup/php:8.4-fpm-nginx

USER root

WORKDIR /var/www/html

RUN install-php-extensions intl

# Image config
ENV WEB_DOCUMENT_ROOT=/var/www/html/public
ENV PHP_DISPLAY_ERRORS=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

# Install dependencies during build (not runtime)
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

# Run post-install scripts and publish assets during build
RUN composer run-script post-autoload-dump \
    && php artisan filament:assets \
    && php artisan storage:link || true

# Ensure storage and cache directories are writable
RUN chown -R www-data:www-data storage bootstrap/cache public/css public/js \
    && chmod -R 775 storage bootstrap/cache

# Copy deploy script to entrypoint directory to run on startup
COPY scripts/00-laravel-deploy.sh /etc/entrypoint.d/99-deploy.sh
RUN chmod +x /etc/entrypoint.d/99-deploy.sh
