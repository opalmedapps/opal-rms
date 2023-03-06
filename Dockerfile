# Build/install JS dependencies
# Pin platform since PhantomJS binary is not available for linux/arm64 architecture
FROM node:16.19.1-alpine3.17 as js-dependencies

WORKDIR /app

# install modules
# allow to cache by not copying the whole application code in (yet)
# see: https://stackoverflow.com/questions/35774714/how-to-cache-the-run-npm-install-instruction-when-docker-build-a-dockerfile
COPY package.json package-lock.json .npmrc ./
RUN npm ci

# Build/install PHP dependencies
FROM composer:2.5.4 as php-dependencies

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --ignore-platform-reqs --optimize-autoloader

# final image
FROM php:8.0.28-apache-bullseye

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install --no-install-recommends -y \
        libmemcached-dev \
        apache2-dev \
    # cleaning up unused files
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

# Install and enable PHP extensions
RUN docker-php-ext-install pdo pdo_mysql \
    # Install memcached
    # see: https://github.com/mlocati/docker-php-extension-installer/
    && curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions memcached

# install Authmemcookie module
RUN mkdir /opt/Apache-Authmemcookie-Module
WORKDIR /opt/Apache-Authmemcookie-Module

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN curl -SL https://github.com/ZenProjects/Apache-Authmemcookie-Module/tarball/master \
        | tar -xz -C /opt/Apache-Authmemcookie-Module --strip-components=1 \
    && autoconf -f && ./configure --with-apxs=/usr/bin/apxs --with-libmemcached=/usr/ && make && make install \
    && echo "LoadModule mod_auth_memcookie_module /usr/lib/apache2/modules/mod_auth_memcookie.so" > /etc/apache2/mods-enabled/00-authCookie.load

# Configure Apache2
RUN ln -sf /dev/stdout /var/log/apache2/error.log \
    && a2enmod rewrite ssl \
    # Change default port to 8080 to allow non-root user to bind port
    # Binding port 80 on CentOS seems to be forbidden for non-root users
    && sed -ri -e 's!Listen 80!Listen 8080!g' /etc/apache2/ports.conf

COPY ./docker/app/ssl.conf /etc/apache2/sites-enabled
COPY ./docker/app/hardwareIpList.list /etc/apache2/

# Install pdflatex and tex packages

WORKDIR /var/www/orms

# Parent needs to be owned by www-data to satisfy npm
RUN chown -R www-data:www-data /var/www/

USER www-data

# copy only the dependencies in...
COPY --from=js-dependencies --chown=www-data:www-data /app/node_modules ./node_modules
COPY --from=php-dependencies --chown=www-data:www-data /app/vendor ./vendor

COPY --chown=www-data:www-data . .

EXPOSE 8080
