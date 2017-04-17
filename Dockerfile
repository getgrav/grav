FROM phusion/baseimage:0.9.16

MAINTAINER Ahumaro Mendoza <ahumaro@ahumaro.com>

CMD ["/sbin/my_init"]

ENV HOME /root
ENV DEBIAN_FRONTEND noninteractive

#Install core packages
RUN apt-get update -q
RUN apt-get upgrade -y -q
RUN apt-get install -y -q php5 php5-cli php5-fpm php5-gd php5-curl php5-apcu ca-certificates nginx git-core
RUN apt-get clean -q && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

#Get Grav
RUN rm -fR /usr/share/nginx/html/
RUN git clone https://github.com/getgrav/grav.git /usr/share/nginx/html/

#Install Grav
WORKDIR /usr/share/nginx/html/
RUN bin/composer.phar self-update
RUN bin/grav install
RUN chown www-data:www-data .
RUN chown -R www-data:www-data *
RUN find . -type f | xargs chmod 664
RUN find . -type d | xargs chmod 775
RUN find . -type d | xargs chmod +s
RUN umask 0002

#Configure Nginx - enable gzip
RUN sed -i 's|# gzip_types|  gzip_types|' /etc/nginx/nginx.conf

#Setup Grav configuration for Nginx
RUN touch /etc/nginx/grav_conf.sh
RUN chmod +x /etc/nginx/grav_conf.sh
RUN echo '#!/bin/bash \n\
    echo "" > /etc/nginx/sites-available/default \n\
    ok="0" \n\
    while IFS="" read line \n\
    do \n\
        if [ "$line" = "    server {" ]; then \n\
            ok="1" \n\
        fi \n\
        if [ "$line" = "}" ]; then \n\
            ok="0" \n\
        fi \n\
        if [ "$ok" = "1" ]; then \n\
            echo "$line" >> /etc/nginx/sites-available/default \n\
        fi \n\
    done < /usr/share/nginx/html/nginx.conf' >> /etc/nginx/grav_conf.sh
RUN /etc/nginx/grav_conf.sh
RUN sed -i \
        -e 's|root   html|root   /usr/share/nginx/html|' \
        -e 's|127.0.0.1:9000;|unix:/var/run/php5-fpm.sock;|' \
    /etc/nginx/sites-available/default

#Setup Php service
RUN mkdir -p /etc/service/php5-fpm
RUN touch /etc/service/php5-fpm/run
RUN chmod +x /etc/service/php5-fpm/run
RUN echo '#!/bin/bash \n\
    exec /usr/sbin/php5-fpm -F' >> /etc/service/php5-fpm/run

#Setup Nginx service
RUN mkdir -p /etc/service/nginx
RUN touch /etc/service/nginx/run
RUN chmod +x /etc/service/nginx/run
RUN echo '#!/bin/bash \n\
    exec /usr/sbin/nginx -g "daemon off;"' >>  /etc/service/nginx/run

#Setup SSH service
RUN sed -i \
        -e 's|#PasswordAuthentication no|PasswordAuthentication no|' \
        -e 's|#UsePAM yes|UsePAM no|' \
    /etc/ssh/sshd_config
RUN rm -f /etc/service/sshd/down
RUN /etc/my_init.d/00_regen_ssh_host_keys.sh

#Expose configuration and content volumes
VOLUME /root/.ssh/ /etc/nginx/ /usr/share/nginx/html/

#Public ports
EXPOSE 80
