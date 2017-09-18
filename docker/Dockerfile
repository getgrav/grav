FROM php:7-apache

ARG DOCKER_UID=1000
ARG GITHUB_OAUTH=none

RUN apt-get update && \
    apt-get install -yq \
    apt-utils \
    zlib1g-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libmcrypt-dev \
    git \
    ant \
    sudo \
    libxml2-dev \
    unzip \
    nano \
    vim \
    supervisor && \
    apt-get install -y libmagickwand-dev --no-install-recommends && \
    docker-php-ext-configure gd --enable-gd-native-ttf --with-jpeg-dir=/usr/lib/x86_64-linux-gnu --with-png-dir=/usr/lib/x86_64-linux-gnu --with-freetype-dir=/usr/lib/x86_64-linux-gnu && \
    docker-php-ext-install pcntl mbstring pdo_mysql zip curl gd intl mcrypt exif bcmath soap sockets && \
    curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin && \
    apt-get clean && rm -r /var/lib/apt/lists/*

RUN pecl install imagick xdebug apcu && \
    docker-php-ext-enable imagick apcu

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY php.ini /usr/local/etc/php/php.ini
COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod ssl

RUN usermod -u $DOCKER_UID www-data \
    && groupmod -g $DOCKER_UID www-data \
    && chsh -s /bin/bash www-data \
    && echo "www-data ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-www-data

RUN mkdir -p /var/www/html/cache
RUN chown -R www-data:www-data /var/www
VOLUME ["/var/www/html/cache", "/var/www/html/logs"]

# composer setup
ENV COMPOSER_HOME "/var/www/.composer"
RUN mkdir -p /var/www/.composer/cache \
    && composer global require 'hirak/prestissimo' \
    && composer config --global optimize-autoloader true \
    && composer config --global github-oauth.github.com $GITHUB_OAUTH \
    && chown -R www-data:www-data /var/www/.composer

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["sudo", "apache2-foreground"]
