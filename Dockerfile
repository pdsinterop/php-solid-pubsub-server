FROM php:7.2

RUN apt-get update \
    && apt-get install -yq --no-install-recommends \
        git \
        zip \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

COPY . /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --working-dir=/app --no-dev --prefer-dist \
    && rm  /usr/local/bin/composer

WORKDIR /app
EXPOSE 8080
CMD ["php", "server/server.php"]
