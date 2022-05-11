FROM php:8.0-apache

WORKDIR /var/www/orms

#install php packages
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions
RUN install-php-extensions pdo_mysql memcached @composer

#install Authmemcookie module
RUN apt update && apt install -y libmemcached-dev apache2-dev

RUN cd /opt \
    && mkdir Apache-Authmemcookie-Module \
    && curl -SL https://github.com/ZenProjects/Apache-Authmemcookie-Module/tarball/master \
        | tar -xz -C Apache-Authmemcookie-Module --strip-components=1 \
    && cd Apache-Authmemcookie-Module \
    && autoconf -f && ./configure --with-apxs=/usr/bin/apxs --with-libmemcached=/usr/ && make && make install \
    && echo "LoadModule mod_auth_memcookie_module /usr/lib/apache2/modules/mod_auth_memcookie.so" > /etc/apache2/mods-enabled/00-authCookie.load

RUN ln -sf /dev/stdout /var/log/apache2/error.log

#apache settings
RUN a2enmod rewrite ssl
COPY ./ssl.conf /etc/apache2/sites-enabled
COPY ./hardwareIpList.list /etc/apache2/

#set up crons

#install pdflatex and tex packages

#install npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

#clean up image
RUN rm -rf /var/lib/apt/lists/*
