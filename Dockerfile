FROM php:7-apache

# install gd
RUN apt-get update && apt-get install -y \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) gd

# install zip
RUN apt-get install -y libzip-dev && pecl install zip && docker-php-ext-enable zip

# install git
RUN apt-get install -y git unzip

# enable rewrite module
RUN a2enmod rewrite

# copy user data
COPY . /var/www/html/
WORKDIR /var/www/html

# install dependencies
RUN bin/grav install

# install plugins
ARG INSTALL_PLUGINS=""
RUN bin/gpm install $INSTALL_PLUGINS

# change owner to www-data
RUN chown -R www-data:www-data .

