FROM php:8.3-cli-bookworm

ARG OMEKA_VERSION=v4.2.1
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        xml \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/start.sh /usr/local/bin/start-omeka

RUN git clone --branch "${OMEKA_VERSION}" --depth 1 https://github.com/omeka/omeka-s.git /var/www/omeka-s \
    && composer install \
        --working-dir=/var/www/omeka-s \
        --no-dev \
        --prefer-install=source \
        --no-interaction \
        --no-progress \
    && mkdir -p /var/www/omeka-s/files /var/www/omeka-s/logs /var/www/omeka-s/modules \
    && chmod +x /usr/local/bin/start-omeka \
    && chown -R www-data:www-data /var/www/omeka-s

WORKDIR /var/www/omeka-s

EXPOSE 8080

USER www-data

CMD ["/usr/local/bin/start-omeka"]
