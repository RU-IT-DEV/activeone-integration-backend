FROM php:8.3-fpm-alpine

RUN set -ex \
  && apk --no-cache add \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    nginx \
    wget \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
        gd \
        pdo \
        pdo_mysql \
        bcmath

RUN mkdir -p /run/nginx
ENV PORT 9000

# Fix OpenSSL "unexpected eof while reading" for Alpine
RUN echo -e "[ssl_sect]\nsystem_default = system_default_sect\n\n[system_default_sect]\nCipherString = DEFAULT@SECLEVEL=1" > /etc/ssl/openssl.cnf
ENV OPENSSL_CONF=/etc/ssl/openssl.cnf

RUN mkdir -p /app
COPY . /app

COPY docker/php/fileupload.ini /usr/local/etc/php/conf.d/uploads.ini

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction

RUN chown -R www-data: .

EXPOSE 8000
CMD [ "php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
