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
    --no-scripts

COPY . .

RUN composer dump-autoload --optimize

RUN php artisan package:discover --ansi

RUN chmod -R 775 storage bootstrap/cache

ENV PORT=8080

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]