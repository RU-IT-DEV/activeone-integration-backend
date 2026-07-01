FROM php:8.3-fpm-alpine

RUN set -ex \
  && apk --no-cache add \
    postgresql-dev

RUN docker-php-ext-install pdo pdo_pgsql

RUN apk add --no-cache nginx wget

RUN mkdir -p /run/nginx
ENV PORT 9000

# COPY docker/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /app
COPY . /app

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction

RUN chown -R www-data: .
# CMD bash -c "chmod -R 777 /var/www && php artisan migrate --seed && php artisan storage:link"
# CMD sh /app/docker/startup.sh

EXPOSE 8000
CMD [ "php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
