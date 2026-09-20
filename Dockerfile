FROM php:8.3-fpm-alpine
RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
RUN composer install --no-dev --optimize-autoloader
CMD php artisan serve --host=0.0.0.0 --port=$PORT
