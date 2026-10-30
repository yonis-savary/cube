FROM php:8.5-fpm AS base

# The owner of Storage/ and .env on the host, which every process runs as (see .env.example)
ARG UID=1000
ARG GID=1000

RUN pecl install apcu \
    && docker-php-ext-enable apcu

RUN apt-get update \
    && apt-get install -y unzip git libzip-dev libpng-dev \
    && docker-php-ext-install zip pcntl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "apc.enable_cli=1" > /usr/local/etc/php/conf.d/apcu.ini \
    && echo "apc.enable=1"    >> /usr/local/etc/php/conf.d/apcu.ini \
    && echo "chdir = /var/www" >> /usr/local/etc/php-fpm.d/www.conf

# -o : the host ids may already belong to a user of the image (gid 20 is `staff` on macOS, `dialout` here)
# php-fpm ignores user/group with a warning when it does not start as root
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g ${GID} www-data \
    && sed -i -e '/^user = /d' -e '/^group = /d' /usr/local/etc/php-fpm.d/www.conf \
    && chown www-data:www-data /var/www

WORKDIR /var/www

COPY --chown=www-data:www-data composer.json composer.lock ./

EXPOSE 9000
CMD ["php-fpm"]


# Development : your project is mounted over /var/www (see docker-compose.override.yml)
FROM base AS dev

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Xdebug : uncomment, then rebuild with `make dev`
# RUN pecl install xdebug \
#     && docker-php-ext-enable xdebug \
#     && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
#     && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

USER www-data

RUN composer install --no-scripts


# Production : your project is copied into the image, which never changes once built
FROM base AS prod

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

USER www-data

RUN composer install --no-dev --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .

RUN composer dump-autoload --no-dev --classmap-authoritative
