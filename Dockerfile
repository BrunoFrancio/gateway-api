FROM php:8.2-fpm

# Configurações PHP
RUN echo "upload_max_filesize=30M" > /usr/local/etc/php/conf.d/upload_config.ini && \
    echo "post_max_size=30M" >> /usr/local/etc/php/conf.d/upload_config.ini && \
    echo "max_execution_time=60" >> /usr/local/etc/php/conf.d/upload_config.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/upload_config.ini

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    libsodium-dev \
    zip \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    bcmath \
    sodium

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar usuário
RUN useradd -ms /bin/bash apiuser && \
    mkdir -p /var/www/html && \
    chown -R apiuser:apiuser /var/www/html

WORKDIR /var/www/html

# Copiar aplicação
COPY --chown=apiuser:apiuser . .

# Trocar para usuário não-root
USER apiuser

# PHP-FPM roda na porta 9000 por padrão
EXPOSE 9000

CMD ["php-fpm"]