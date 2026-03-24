FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libsodium-dev \
    && docker-php-ext-install pdo pdo_mysql zip opcache sodium

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader

RUN useradd -m -u 1000 appuser
RUN chown -R appuser:appuser /var/www
USER appuser

EXPOSE 9000

CMD ["php-fpm"]