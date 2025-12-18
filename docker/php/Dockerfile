FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    linux-headers \
    && docker-php-ext-install pdo pdo_mysql zip

# Install Redis and PCOV extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www

USER www-data
