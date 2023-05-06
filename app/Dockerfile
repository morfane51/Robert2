FROM php:8-apache-buster

ENV ROBERT2_VERSION 0.20.6

ENV PHP_INI_DATE_TIMEZONE 'Europe/Paris'
ENV PHP_INI_MEMORY_LIMIT 256M
ENV TZ=Europe/Paris

RUN apt-get update -y \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        libc-client-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libkrb5-dev \
        libldap2-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        default-mysql-client \
        postgresql-client \
        unzip \
        iputils-ping \
    && apt-get autoremove -y \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) calendar intl mysqli pdo_mysql gd soap zip bcmath gettext \
    && docker-php-ext-configure pgsql -with-pgsql \
    && docker-php-ext-install pdo_pgsql pgsql \
    && mv ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini \
    && a2enmod rewrite

RUN printf "\n" | pecl install apcu \
    && docker-php-ext-enable apcu --ini-name 10-docker-php-ext-apcu.ini

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN printf '[PHP]\ndate.timezone = "Europe/Paris"\n' > /usr/local/etc/php/conf.d/tzone.ini

RUN apt-get install -y git
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - & apt-get install -y nodejs npm

RUN chown -R www-data:www-data /var/www

RUN rm -rf /var/lib/apt/lists/*

EXPOSE 80

CMD ["apache2-foreground"]
