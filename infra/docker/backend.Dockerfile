FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.3-apache

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN a2enmod headers rewrite \
    && { \
        echo '<VirtualHost *:80>'; \
        echo '    ServerName localhost'; \
        echo '    DocumentRoot /var/www/html/public'; \
        echo '    <Directory /var/www/html/public>'; \
        echo '        Require all granted'; \
        echo '        FallbackResource /index.php'; \
        echo '    </Directory>'; \
        echo '    ErrorLog /proc/self/fd/2'; \
        echo '    CustomLog /proc/self/fd/1 combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf \
    && mkdir -p var \
    && chown -R www-data:www-data /var/www/html
