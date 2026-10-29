FROM composer:2 AS vendor
WORKDIR /app
COPY apps/cms/ .
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts \
    && composer dump-autoload --optimize --classmap-authoritative

FROM php:8.2-fpm-alpine
RUN apk add --no-cache icu-dev postgresql-dev \
    && docker-php-ext-install intl opcache pdo_pgsql
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/cms
COPY --from=vendor /app/vendor ./vendor
COPY apps/cms/ .
RUN mkdir -p var/cache var/log && chown -R www-data:www-data var

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
