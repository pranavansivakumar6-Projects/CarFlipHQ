FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork

ENV APP_BASE_PATH=""
ENV PORT=8080

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p uploads/expenses uploads/tasks uploads/cars \
    && chown -R www-data:www-data uploads

EXPOSE 8080

CMD ["sh", "-c", "set -e; : \"${PORT:=8080}\"; sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${PORT}>/\" /etc/apache2/sites-available/000-default.conf; echo \"Starting Apache on port ${PORT}\"; apache2-foreground"]
