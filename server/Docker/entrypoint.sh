#!/bin/sh
set -e

# Storage is mounted from the host, whose owner hides the one given in the image
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/Storage
fi

exec docker-php-entrypoint "$@"
