FROM composer:2

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p database bootstrap/cache storage/logs \
    && touch database/database.sqlite \
    && chmod -R 775 bootstrap/cache storage database

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --seed --force && php artisan l5-swagger:generate && php artisan serve --host=0.0.0.0 --port=8000"]
