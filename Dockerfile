FROM amazon/aws-cli:2.13.0 AS awscli

ARG AWS_ACCESS_KEY_ID
ARG AWS_SECRET_ACCESS_KEY
ENV AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID} \
    AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}

RUN aws s3 cp \
      s3://keboola-drivers/hive-odbc/ClouderaHiveODBC-2.6.13.1013-1.x86_64.rpm \
      /tmp/hive-odbc.rpm

FROM php:8.2-cli-buster

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/php/composer-install.sh /tmp/composer-install.sh

# https://github.com/debuerreotype/docker-debian-artifacts/issues/24
RUN mkdir -p /usr/share/man/man1 && \
    apt-get update && apt-get install -y --no-install-recommends \
        alien \
        ssh \
        git \
        locales \
        unzip \
        unixodbc \
        unixodbc-dev \
        libicu-dev \
        libsasl2-dev \
        libsasl2-2 \
        libsasl2-modules \
        libsasl2-modules-db \
        libsasl2-modules-sql \
        libsasl2-modules-gssapi-mit \
        libsasl2-modules-ldap \
        krb5-user \
        libzip-dev \
        # keytool is in JRE
        default-jre \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

# INTL
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl

# ZLIB
RUN docker-php-ext-install zip

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

# Clouder Hive Driver
COPY --from=awscli /tmp/hive-odbc.rpm /tmp/hive-odbc.rpm
RUN alien -i /tmp/hive-odbc.rpm \
    && rm /tmp/hive-odbc.rpm \
    && cp /opt/cloudera/hiveodbc/Setup/odbc.ini /etc/odbc.ini \
    && cp /opt/cloudera/hiveodbc/Setup/odbcinst.ini /etc/odbcinst.ini

# Create odbc logs dir
RUN mkdir -p /var/log/hive-odbc \
    && chown root:root /var/log/hive-odbc \
    && chmod 755 /var/log/hive-odbc

# Enable verbose (full debug) logging in the Cloudera Hive ODBC driver
RUN sed -i '/^\[Cloudera ODBC Driver for Apache Hive 64-bit\]/a \
    LogLevel = 6\n\
    LogPath = /var/log/hive-odbc/' \
    /etc/odbcinst.ini

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8
ENV KRB5_CONFIG='/tmp/php-krb5.conf'
ENV KRB5_KEYTAB='/tmp/php-krb5.keytab'
ENV BUNDLED_FILES_PATH='/var/bundled-files'

# Some large certificates cannot be in stack parameters and must be packed in the component.
RUN mkdir "$BUNDLED_FILES_PATH" && \
    chown www-data:www-data "$BUNDLED_FILES_PATH"

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
