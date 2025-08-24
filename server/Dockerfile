FROM php:8.2-apache

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

RUN a2enmod rewrite

COPY . /var/www

WORKDIR /var/www
RUN sed -i 's|/var/www/html|/var/www/Public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|/var/www/Public|g' /etc/apache2/apache2.conf \
    && sed -i 's|Directory /var/www/|Directory /var/www/Public/|g' /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www

EXPOSE 80
