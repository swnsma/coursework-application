FROM nimmis/apache-php7

MAINTAINER Aleksandr Kravchuk <swnsma@gmail.com>

RUN apt-get update && \
apt-get install -y php-memcache

COPY server.crt /etc/apache2/ssl/server.crt
COPY server.key /etc/apache2/ssl/server.key
RUN a2enmod rewrite
RUN a2enmod ssl

EXPOSE 80
EXPOSE 443

ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]

COPY . /var/www/html/application
