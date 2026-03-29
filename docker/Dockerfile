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

# Convierte CRLF a LF en todos los archivos de config de docker
RUN find /var/www/docker -type f | xargs sed -i 's/\r//'

# Copia el .env.example como .env si no existe .env
RUN if [ ! -f /var/www/.env ]; then cp /var/www/.env.example /var/www/.env; fi

RUN composer install --no-interaction --optimize-autoloader

# Permisos correctos desde el build
RUN chown -R www-data:www-data /var/www/storage \
    /var/www/bootstrap/cache \
    /var/www/database \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]