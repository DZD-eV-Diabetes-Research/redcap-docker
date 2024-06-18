FROM php:8.2-apache-buster

## https://hub.docker.com/_/php/tags?page=1&name=apache

ARG DEBIAN_FRONTEND=noninteractive


# Install Webserver dep
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
    libyaml-dev \
    # cron req
    busybox-static \
    # yaml support for user prov
    && pecl install yaml \
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
COPY container_assets /opt/redcap-docker/assets

RUN chmod -R +x /opt/redcap-docker/assets/scripts/

# Deploy php.ini
RUN mv /opt/redcap-docker/assets/config/php/php.ini /usr/local/etc/php/php.ini && \
    chmod 600 /usr/local/etc/php/php.ini && \
    rm -r /opt/redcap-docker/assets/config/php
# Deploy apache virtual host
RUN rm -R /etc/apache2/sites-enabled && \
    mv /opt/redcap-docker/assets/config/apache2/sites-enabled /etc/apache2/sites-enabled && \
    chmod -R 644 /etc/apache2/sites-enabled
# Deploy apache config
RUN mv /opt/redcap-docker/assets/config/apache2/conf-enabled/* /etc/apache2/conf-enabled && \
    chmod -R 644 /etc/apache2/conf-enabled && \
    rm -r /opt/redcap-docker/assets/config/apache2

ENV WWW_DATA_UID=33
ENV WWW_DATA_GID=33
ENV FIX_REDCAP_DIR_PERMISSIONS=true
ENV PHP_INI_SCAN_DIR=/usr/local/etc/php.d:/config/php/custom_inis:
ENV SERVER_NAME localhost
ENV SERVER_ADMIN root
ENV SERVER_ALIAS localhost
ENV APACHE_RUN_HOME /var/www
ENV APACHE_DOCUMENT_ROOT /var/www/html
ENV APACHE_ERROR_LOG /dev/stdout
ENV APACHE_ACCESS_LOG /dev/stdout
ENV REDCAP_INSTALL_ENABLE=true
ENV REDCAP_INSTALL_SQL_SCRIPT_PATH=/config/redcap/install/install.sql
ENV REDCAP_SUSPEND_SITE_ADMIN=false

# USER Provisioning
ENV ENABLE_USER_PROV=true
ENV USER_PROV_FILE_DIR=/opt/redcap-docker/users
RUN mkdir -p $USER_PROV_FILE_DIR
ENV USER_PROV_OVERWRITE_EXISTING=false
# Application default config
ENV RCCONF_redcap_base_url=http://localhost
ENV RCCONF_password_algo=sha512
ENV RCCONF_hook_functions_file=/var/www/html/hook_functions.php
# cron default config
ENV CRON_MODE=false
ENV CRON_INTERVAL="*/5 * * * *"
ENV CRON_RUN_JOB_ON_START=false
ENV CRON_HEALTH_STATE_FILE=/tmp/cron-health.txt
# register cron command
RUN ln -s /opt/redcap-docker/assets/scripts/cron-job.sh /usr/bin/redcap-cron
# Enable apache extensions
RUN a2enmod proxy_http
RUN a2enmod rewrite

# log mstmp (sendmail) to stdout
ENV MSMTP_logfile=-

# define healthcheck
HEALTHCHECK --interval=5s --timeout=3s CMD /opt/redcap-docker/assets/scripts/healthcheck.sh

CMD ["/opt/redcap-docker/assets/scripts/container_start.sh"]
