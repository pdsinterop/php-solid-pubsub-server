FROM php:8.3

RUN apt-get update \
    && apt-get install -yq --no-install-recommends \
        git \
        zip \
        zlib1g-dev \
        openssl \
    && rm -rf /var/lib/apt/lists/*

COPY . /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --working-dir=/app --no-dev --prefer-dist \
    && rm  /usr/local/bin/composer

WORKDIR /app/server/certs
RUN openssl genrsa -des3 -passout pass:x -out server.pass.key 2048 && \
  openssl rsa -passin pass:x -in server.pass.key -out server.key && \
  rm server.pass.key && \
  openssl req -new -key server.key -out server.csr -subj "/C=UK/ST=Warwickshire/L=Leamington/O=PDSInterop/OU=PDSInterop/CN=solid.pdsinterop.org" && \
  openssl x509 -req -days 365 -in server.csr -signkey server.key -out server.crt 

WORKDIR /app/server/
EXPOSE 8080
CMD ["php", "server.php"]
