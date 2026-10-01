FROM php:8.4-cli-alpine
RUN apk add --no-cache libpq libzip-dev postgresql-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pgsql bcmath zip pcntl \
 && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction
COPY . .
RUN php artisan config:cache && php artisan route:cache && php artisan event:cache || true
EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:8000/up || exit 1
