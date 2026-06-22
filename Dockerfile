FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql

ENV APP_BASE_PATH=""

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p uploads/expenses uploads/tasks uploads/cars \
    && chown -R www-data:www-data uploads

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT:-80}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
