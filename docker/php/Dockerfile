FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libssl-dev \
    libsodium-dev \
    libpq-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql zip opcache sodium

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "extension=pdo_mysql.so" > /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader

RUN useradd -m -u 1000 appuser
RUN chown -R appuser:appuser /var/www
USER appuser

EXPOSE 9000

CMD ["php-fpm"]