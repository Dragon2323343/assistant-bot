FROM php:7.2-fpm

# 👉 Фиксим устаревшие репозитории
RUN sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list && \
    sed -i '/security.debian.org/d' /etc/apt/sources.list && \
    apt-get update && apt-get install -y \
        build-essential \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        locales \
        zip \
        jpegoptim optipng pngquant gifsicle \
        vim unzip git curl libzip-dev

# 👉 PHP расширения
RUN docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# 👉 Composer из официального контейнера Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# 👉 Рабочая директория
WORKDIR /var/www

# 👉 Копируем всё
COPY . /var/www

# 👉 Ставим зависимости Laravel
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 👉 Даём права www-data
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
