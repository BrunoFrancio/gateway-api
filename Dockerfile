FROM php:8.2-fpm
RUN apt-get update && apt-get install -y \
    git unzip curl libpq-dev libzip-dev zip supervisor \
 && docker-php-ext-install pdo pdo_pgsql zip bcmath \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
