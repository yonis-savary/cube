FROM php:8.2-fpm

RUN pecl install apcu \
    && docker-php-ext-enable apcu

RUN apt-get update \
    && apt-get install -y unzip git libzip-dev libpng-dev \
    && docker-php-ext-install zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "apc.enable_cli=1" > /usr/local/etc/php/conf.d/apcu.ini \
    && echo "apc.enable=1"    >> /usr/local/etc/php/conf.d/apcu.ini \
    && echo "chdir = /var/www" >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

COPY composer.json .
COPY composer.lock .
RUN composer install

COPY . .

RUN chown -R www-data:www-data /var/www
EXPOSE 9000

CMD ["php-fpm"]
