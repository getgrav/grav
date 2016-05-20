FROM php:5.6-apache

RUN curl -sL https://deb.nodesource.com/setup_6.x | bash

RUN echo deb http://http.debian.net/debian jessie-backports main >> /etc/apt/sources.list

RUN apt-get update --fix-missing && \
    apt-get install -y \
    zlib1g-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libmcrypt-dev \
    libxml2-dev \
    libmagickwand-dev \
    git \
    nodejs \
    unzip \
    bzip2 \
    rubygems \
    ruby-dev \
    php5-cli \
    php5-common \
    php5-sqlite \
    rsyslog \
    libnotify-bin \
    sudo

RUN docker-php-ext-install mbstring pdo_mysql zip gd curl intl mcrypt soap opcache json

RUN pecl install imagick && docker-php-ext-enable imagick

# Install and enable xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin
RUN apt-get clean && rm -r /var/lib/apt/lists/*

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.conf
COPY php.ini /usr/local/etc/php/php.ini
COPY vhost.conf /etc/apache2/
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN a2enmod rewrite
RUN a2enmod ssl

# https://github.com/sameersbn/docker-gitlab/issues/396
RUN sed -i 's/host = uri.host/host = "http:\/\/rubygems.org"/g' /usr/lib/ruby/2.1.0/rubygems/remote_fetcher.rb

RUN usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && chown www-data:www-data /var/www \
    && chsh -s /bin/bash www-data \
    && echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-www-data

RUN chown 1000:1000 /var/www
RUN mkdir -p /var/www/html/cache
RUN chown -R 1000:1000 /var/www/html/cache
VOLUME ["/var/www/html/app/cache"]

USER www-data

CMD ["sudo", "apache2-foreground"]
