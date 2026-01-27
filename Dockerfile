FROM serversideup/php:8.4-fpm-nginx

USER root

WORKDIR /var/www/html

RUN install-php-extensions intl

COPY . .

# Ensure storage and cache directories are writable
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Image config
ENV WEB_DOCUMENT_ROOT=/var/www/html/public
ENV PHP_DISPLAY_ERRORS=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy deploy script to entrypoint directory to run on startup
COPY scripts/00-laravel-deploy.sh /etc/entrypoint.d/99-deploy.sh
RUN chmod +x /etc/entrypoint.d/99-deploy.sh
