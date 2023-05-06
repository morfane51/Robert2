FROM php:8-fpm

RUN apt-get update && apt-get install -y zlib1g-dev g++ git libicu-dev zip libzip-dev libpng-dev libjpeg-dev \
    && docker-php-ext-install intl opcache pdo pdo_mysql \
    && pecl install apcu && docker-php-ext-enable apcu \
    && docker-php-ext-configure zip && docker-php-ext-install zip \
    && docker-php-ext-install exif && docker-php-ext-enable exif \
    && docker-php-ext-configure gd --with-jpeg=/usr/include/ && docker-php-ext-install gd

WORKDIR /var/www/project

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

EXPOSE 9001
