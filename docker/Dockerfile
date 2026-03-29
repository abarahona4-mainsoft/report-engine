FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y supervisor \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY . .

# Convierte CRLF a LF en archivos de docker
RUN find /var/www/docker -type f | xargs sed -i 's/\r//'

# Copia .env si no existe
RUN if [ ! -f /var/www/.env ]; then cp /var/www/.env.example /var/www/.env; fi

RUN composer install --no-interaction --optimize-autoloader

# Copia supervisord limpio directo al destino
COPY docker/supervisord.conf /tmp/supervisord.conf
RUN sed -i 's/\r//' /tmp/supervisord.conf \
    && cp /tmp/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000