FROM php:8.4-apache-bookworm

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
    # mysql client for upgrade backup/restore (mysqldump + mysql)
    default-mysql-client \
    # yaml support for user prov
    && pecl install yaml \
    # INSTALL IMAGICK
    #&& pecl install imagick \
    #&& docker-php-ext-enable imagick \
    # INSTALL MYSQLI AND OTHER DOCKER FUN
    && docker-php-ext-install gd zip mysqli \
    ### cleanup
    && rm -r /var/lib/apt/lists/*

#  Install imagick from source as there is no PECL version
RUN apt-get update -qq && \
    apt-get -yq --no-install-recommends install git && \
    git clone https://github.com/Imagick/imagick.git --depth 1 /tmp/imagick && \
    cd /tmp/imagick && \
    git fetch origin master && \
    git switch master && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    docker-php-ext-enable imagick && \
    # cleanup
    apt-get -yq purge git && \
    rm -r /tmp/imagick


# Update ImageMagick policy
RUN sed -i 's/policy\ domain=\"coder\" rights=\"none\" pattern=\"PDF\"/policy domain=\"coder\" rights=\"read\" pattern=\"PDF\"/g' /etc/ImageMagick-6/policy.xml

# REDCap Container configs,templates and scripts
COPY container_assets /opt/redcap-docker/assets

RUN chmod -R +x /opt/redcap-docker/assets/scripts/

# Deploy php.ini
RUN mv /opt/redcap-docker/assets/config/php/php.ini /usr/local/etc/php/php.ini && \
    chmod 644 /usr/local/etc/php/php.ini
# Deploy other php ini files
RUN mv /opt/redcap-docker/assets/config/php/conf.d/*  /usr/local/etc/php/conf.d/ && \
    chmod 644 /usr/local/etc/php/conf.d/* && \
    rm -r /opt/redcap-docker/assets/config/php
# Deploy apache virtual host
RUN rm -R /etc/apache2/sites-enabled && \
    mv /opt/redcap-docker/assets/config/apache2/sites-enabled /etc/apache2/sites-enabled && \
    chmod -R 644 /etc/apache2/sites-enabled
# Deploy apache config
RUN mv /opt/redcap-docker/assets/config/apache2/conf-enabled/* /etc/apache2/conf-enabled && \
    chmod -R 644 /etc/apache2/conf-enabled && \
    rm -r /opt/redcap-docker/assets/config/apache2


# Desired REDCap version — when set the container auto-installs or auto-upgrades to this version.
# Set REDCAP_AUTO_UPGRADE=true to also enable automatic upgrades on boot (default: false).
ENV REDCAP_VERSION=
ENV REDCAP_AUTO_UPGRADE=false
# Disable REDCap's browser-based Easy Upgrade by keeping the webroot read-only for www-data.
# Easy Upgrade requires the web process to write PHP files to the webroot, which is a security
# risk on production servers. Set to true only if you specifically need the in-browser upgrade
# tool — the container's own redcap-upgrade command does NOT require this to be enabled.
ENV REDCAP_EASY_UPGRADE_ENABLE=false
# Run a file-integrity check against a canonical REDCap download on every boot.
# Disabled by default because it requires a download and slows startup.
# Aborts boot if tampering is detected.
ENV REDCAP_INTEGRITY_CHECK_ON_BOOT=false

ENV REDCAP_DOCKER_SCRIPTS_DEBUG=false
ENV WWW_DATA_UID=33
ENV WWW_DATA_GID=33
ENV AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION=/opt/redcap-docker/sql_scripts_run_once
RUN mkdir -p $AT_BOOT_RUN_SQL_SCRIPTS_FROM_LOCATION
ENV FIX_REDCAP_DIR_PERMISSIONS=true
ENV PHP_INI_SCAN_DIR=:/config/php/custom_inis
ENV SERVER_NAME=localhost
ENV SERVER_ADMIN=root
ENV SERVER_ALIAS=localhost
ENV APACHE_RUN_HOME=/var/www
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV APACHE_ERROR_LOG=/dev/stdout
ENV APACHE_ACCESS_LOG=/dev/stdout
ENV REDCAP_INSTALL_ENABLE=true
ENV REDCAP_INSTALL_SQL_SCRIPT_PATH=/config/redcap/install/install.sql
ENV REDCAP_SUSPEND_SITE_ADMIN=false

# REDCap community portal credentials used by the in-place upgrader
ENV REDCAP_COMMUNITY_USER=
ENV REDCAP_COMMUNITY_PASSWORD=

# Directory where the upgrader stores pre-upgrade database backups
ENV REDCAP_UPGRADE_BACKUP_DIR=/opt/redcap-docker/backups
RUN mkdir -p $REDCAP_UPGRADE_BACKUP_DIR

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
# register in-place upgrade and install commands
RUN ln -s /opt/redcap-docker/assets/scripts/redcap_upgrade.sh /usr/bin/redcap-upgrade
RUN ln -s /opt/redcap-docker/assets/scripts/redcap_install.sh /usr/bin/redcap-install
# register file integrity checker
RUN ln -s /opt/redcap-docker/assets/scripts/redcap_integrity_check.sh /usr/bin/redcap-integrity-check
# Enable apache extensions
RUN a2enmod proxy_http
RUN a2enmod rewrite
RUN a2enmod headers

# log mstmp (sendmail) to stdout
ENV MSMTP_logfile=-

# define healthcheck
HEALTHCHECK --interval=5s --timeout=3s CMD /opt/redcap-docker/assets/scripts/healthcheck.sh

CMD ["/opt/redcap-docker/assets/scripts/container_start.sh"]
