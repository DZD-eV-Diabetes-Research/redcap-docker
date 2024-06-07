FROM php:8.2-apache-buster

## https://hub.docker.com/_/php/tags?page=1&name=apache

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update -qq && \
    apt-get -yq --no-install-recommends install \
    msmtp-mta \
    ca-certificates \
    git \
    vim \
    ssh \
    wget \
    libpng-dev \
    libzip-dev \
    zip \
    ghostscript \
    libmagickwand-dev \
    # INSTALL IMAGICK
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    # INSTALL MYSQLI AND OTHER DOCKER FUN
    && docker-php-ext-install gd zip mysqli \
    ### cleanup
    && rm -r /var/lib/apt/lists/*

# Update ImageMagick policy
RUN sed -i 's/policy\ domain=\"coder\" rights=\"none\" pattern=\"PDF\"/policy domain=\"coder\" rights=\"read\" pattern=\"PDF\"/g' /etc/ImageMagick-6/policy.xml

# REDCap Container configs,templates and scripts
COPY container_assets /etc/redcap_container_assets

RUN chmod -R +x /etc/redcap_container_assets/scripts/

# Deploy php.ini
RUN mv /etc/redcap_container_assets/config/php/php.ini /usr/local/etc/php/php.ini && \
    chmod 600 /usr/local/etc/php/php.ini && \
    rm -r /etc/redcap_container_assets/config/php
# Deploy apache virtual host
RUN rm -R /etc/apache2/sites-enabled && \
    mv /etc/redcap_container_assets/config/apache2/sites-enabled /etc/apache2/sites-enabled && \
    chmod -R 644 /etc/apache2/sites-enabled
# Deploy apache config
RUN mv /etc/redcap_container_assets/config/apache2/conf-enabled/* /etc/apache2/conf-enabled && \
    chmod -R 644 /etc/apache2/conf-enabled && \
    rm -r /etc/redcap_container_assets/config/apache2

ENV PHP_INI_SCAN_DIR=/usr/local/etc/php.d:/config/php/custom_inis:
ENV SERVER_NAME localhost
ENV SERVER_ADMIN root
ENV SERVER_ALIAS localhost
ENV APACHE_RUN_HOME /var/www
ENV APACHE_DOCUMENT_ROOT /var/www/html
ENV APACHE_ERROR_LOG /dev/stdout
ENV APACHE_ACCESS_LOG /dev/stdout
ENV REDCAP_INSTALL_SQL_SCRIPT_PATH=/config/redcap/install/install.sql

# Enable apache extensions
RUN a2enmod proxy_http
RUN a2enmod rewrite


CMD ["/etc/redcap_container_assets/scripts/container_start.sh"]
