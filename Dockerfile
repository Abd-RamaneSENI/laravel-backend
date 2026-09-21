FROM php:8.3-fpm-alpine
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
RUN composer install --no-dev --optimize-autoloader
# Force l'execution des migrations et initialise les donnees au premier demarrage.
CMD php artisan migrate --force && (php artisan db:seed --force || true) && php artisan serve --host=0.0.0.0 --port=$PORT
