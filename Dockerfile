FROM php:8.3-fpm-alpine

RUN set -ex \
  && apk --no-cache add \
    postgresql-dev

RUN apk add --no-cache \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
        gd \
        pdo \
        pdo_mysql

RUN docker-php-ext-install bcmath

RUN docker-php-ext-install pdo pdo_mysql

RUN apk add --no-cache nginx wget

RUN mkdir -p /run/nginx
ENV PORT 9000

# COPY docker/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /app
COPY . /app

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction

RUN chown -R www-data: .
# CMD bash -c "chmod -R 777 /var/www && php artisan migrate --seed && php artisan storage:link"
# CMD sh /app/docker/startup.sh

EXPOSE 8000
CMD [ "php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
