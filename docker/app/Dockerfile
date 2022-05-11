FROM php:8.0-apache

WORKDIR /var/www/orms

#install php packages
RUN curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql memcached @composer

#install npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install --no-install-recommends -y nodejs \
    && rm -rf /var/lib/apt/lists/*

#install Authmemcookie module
RUN apt-get update \
    && apt-get install --no-install-recommends -y libmemcached-dev apache2-dev \
    && rm -rf /var/lib/apt/lists/*

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

#install pdflatex and tex packages
