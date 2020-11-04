FROM php:7.2
RUN apt-get update && \
    apt-get install -y \
        git \
        zlib1g-dev 
WORKDIR /tls
WORKDIR /install
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
ADD . /app
WORKDIR /app
RUN php /install/composer.phar install --no-dev --prefer-dist
EXPOSE 8080
RUN php server/server.php