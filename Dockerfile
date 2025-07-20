FROM php:8.0-fpm

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libonig-dev \
        libzip-dev \
        libmemcached-dev \
        libmcrypt-dev \
        libc-dev \
        libssl-dev \
        libz-dev \
    && docker-php-ext-install -j$(nproc) iconv mbstring mysqli pdo_mysql zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www

COPY . /var/www

RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
