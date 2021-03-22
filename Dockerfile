FROM php:7.4-fpm-buster

ENV PHP_USE_OPCACHE 0

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        libicu-dev \
        libpq-dev \
        libxpm-dev \
        libvpx-dev \
		libmagickwand-dev \
        libzip-dev \
        unzip \
		git \
		procps \
        supervisor \
        vim

RUN pecl install imagick \
    && pecl install redis \
    && pecl install mcrypt-1.0.3 \
	&& docker-php-ext-enable imagick \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable mcrypt \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) intl \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) pgsql \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && docker-php-ext-install -j$(nproc) exif \
    && docker-php-ext-install -j$(nproc) opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-xpm

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer \
    && mkdir -p /var/log/supervisord/
RUN \
    docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure mysqli --with-mysqli=mysqlnd \
    && docker-php-ext-install pdo_mysql

CMD ["tail", "-f"]