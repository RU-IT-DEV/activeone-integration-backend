FROM php:8.3-fpm-alpine

RUN apt-get update && apt-get install -y \
    build-essential \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    nodejs \
    npm \
    default-mysql-server \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
  pdo \
  pdo_pgsql \
  mbstring \
  zip \
  exif \
  pcntl \
  gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-scripts

RUN chown -R www-data: /app

CMD ["php", "artisan", "serve", "--host=0.0.0.0"]