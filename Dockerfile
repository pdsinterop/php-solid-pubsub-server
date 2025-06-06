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
  openssl req -new -key server.key -out server.csr -subj "/C=NL/ST=Overijssel/L=Enschede/O=PDSInterop/OU=PDSInterop/CN=pubsub" && \
  openssl x509 -req -days 365 -in server.csr -signkey server.key -out server.crt 

# Create a custom user with UID 1234 and GID 1234
RUN groupadd -g 1234 pubsubgroup && \
    useradd -m -u 1234 -g pubsubgroup pubsubuser

USER pubsubuser

WORKDIR /app/server/
EXPOSE 8080
CMD ["php", "server.php"]

LABEL org.opencontainers.image.source = "https://github.com/pdsinterop/php-solid-pubsub-server"
