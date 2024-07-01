#syntax=docker/dockerfile:1.4

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# Prod image
FROM php:8.2.11-fpm-alpine3.18 AS app_php

ARG APP_UID=1000
ARG APP_GID=1000
RUN addgroup -S magali -g ${APP_GID} && adduser -u ${APP_UID} -S -D -G magali magali

WORKDIR /app

ENV APP_ENV=prod APP_DEBUG=0 PHP_MEMORY_LIMIT=128M COMPOSER_ALLOW_SUPERUSER=1

# https://github.com/mlocati/docker-php-extension-installer#supported-php-extensions
COPY --from=mlocati/php-extension-installer:2.1.58 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl opcache pdo_mysql @composer-2.6.5

# Git dep for composer
RUN apk add --no-cache git

# FPM configuration
COPY docker/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/fpm/app.ini $PHP_INI_DIR/conf.d/
COPY docker/fpm/app.prod.ini $PHP_INI_DIR/conf.d/

# prevent the reinstallation of vendors at every changes in the source code
COPY app/composer.* app/symfony.* ./
RUN set -eux; \
    if [ -f composer.json ]; then \
        composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
        composer clear-cache; \
    fi

# copy sources
COPY /app /app

RUN set -eux; \
    mkdir -p var/cache var/log; \
    if [ -f composer.json ]; then \
        composer dump-autoload --classmap-authoritative --no-dev; \
        composer dump-env prod; \
        composer run-script --no-dev post-install-cmd; \
        chown -R magali:magali /app; \
        chmod +x bin/console; sync; \
    fi

ENV PATH="${PATH}:/app/bin"

USER magali

###### DEV IMAGE ######
# bash + composer + symfony + xdebug
FROM app_php AS app_php_dev

USER root

# Default configuration
ENV APP_ENV="dev" APP_DEBUG=1

RUN rm $PHP_INI_DIR/conf.d/app.prod.ini; \
    mv "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php.ini-production"; \
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini";
COPY docker/fpm/app.dev.ini $PHP_INI_DIR/conf.d/

COPY /docker/scripts/symfony-installer.sh /usr/local/bin/symfony-installer
RUN chmod +x /usr/local/bin/symfony-installer

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN install-php-extensions xdebug-3.2.2; \
    apk add --update --no-cache bash; \
    ln -sf python3 /usr/bin/python; \
    curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | bash; \
    apk add --no-cache symfony-cli; \
    if [ -f composer.json ]; then \
        composer install --prefer-dist --no-progress; \
        composer clear-cache; \
    fi

RUN rm -f .env.local.php

USER magali
