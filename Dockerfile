FROM dunglas/frankenphp:php8.3

RUN install-php-extensions \
    pdo_pgsql \
    gd \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

COPY . .

RUN chmod -R 775 storage bootstrap/cache

ENV SERVER_NAME=:8080

EXPOSE 8080

CMD ["php", "artisan", "octane:frankenphp"]