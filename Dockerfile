FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip curl libpq-dev libzip-dev zip \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql zip bcmath opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN { \
  echo "upload_max_filesize=30M"; \
  echo "post_max_size=30M"; \
  echo "max_execution_time=60"; \
} > /usr/local/etc/php/conf.d/custom.ini

RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=1"; \
  echo "opcache.validate_timestamps=1"; \
  echo "opcache.max_accelerated_files=20000"; \
  echo "opcache.memory_consumption=128"; \
  echo "opcache.interned_strings_buffer=16"; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN useradd -ms /bin/bash apiuser

WORKDIR /var/www/html
