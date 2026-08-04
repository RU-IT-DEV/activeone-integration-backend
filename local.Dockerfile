# Use an official PHP runtime as a parent image
FROM php:8.3-cli

# Install system dependencies and PHP extensions
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

# Install Node.js and npm
# RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -

RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

# Copy the Laravel application code
COPY . /app

# Set working directory
WORKDIR /app

RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction
RUN composer require spatie/laravel-ignition --dev
RUN composer dump-autoload --optimize \
    && php artisan optimize:clear \
    && php artisan config:clear \
    && php artisan cache:clear \
    && chmod -R 777 storage bootstrap/cache

# Run Laravel development server
CMD php artisan serve --env=local --port=8000 --host=0.0.0.0
# CMD [ "tail", "-f" ]
