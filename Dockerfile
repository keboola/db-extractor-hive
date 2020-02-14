FROM php:7-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/php/composer-install.sh /tmp/composer-install.sh
COPY docker/php/clouderahiveodbc_2.5.25.1020-2_amd64.deb /tmp/hive-odbc.deb

RUN apt-get update && apt-get install -y --no-install-recommends \
        ssh \
        git \
        locales \
        unzip \
        wait-for-it \
        unixodbc \
        unixodbc-dev \
        libsasl2-dev \
        libsasl2-2 \
        libsasl2-modules \
        libsasl2-modules-db \
        libsasl2-modules-sql \
        libsasl2-modules-gssapi-mit \
        libsasl2-modules-ldap \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

# PHP ODBC
# https://github.com/docker-library/php/issues/103#issuecomment-353674490
RUN set -ex; \
    docker-php-source extract; \
    { \
        echo '# https://github.com/docker-library/php/issues/103#issuecomment-353674490'; \
        echo 'AC_DEFUN([PHP_ALWAYS_SHARED],[])dnl'; \
        echo; \
        cat /usr/src/php/ext/odbc/config.m4; \
    } > temp.m4; \
    mv temp.m4 /usr/src/php/ext/odbc/config.m4; \
    docker-php-ext-configure odbc --with-unixODBC=shared,/usr; \
    docker-php-ext-install odbc; \
    docker-php-source delete

# Hive Driver
RUN dpkg -i /tmp/hive-odbc.deb
RUN cp /opt/cloudera/hiveodbc/Setup/odbc.ini /etc/odbc.ini
RUN cp /opt/cloudera/hiveodbc/Setup/odbcinst.ini /etc/odbcinst.ini

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/
# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader
# copy rest of the app
COPY . /code/
# run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]
