FROM php:8.5-fpm-alpine AS base

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . .

RUN rm -rf bootstrap/cache/*.php && \
    php artisan storage:link \
    && php artisan package:discover --ansi

FROM php:8.5-fpm-alpine AS app

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=base /var/www/html /var/www/html

COPY docker/php.prod.ini /usr/local/etc/php/conf.d/prod.ini

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
