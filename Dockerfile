FROM composer:2.10.3 AS composer
FROM php:8.5.12-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libpq-dev libonig-dev unzip git \
    && docker-php-ext-install intl mbstring pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/querylens.ini
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
