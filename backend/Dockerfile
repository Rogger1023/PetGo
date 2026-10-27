FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       curl git unzip libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql bcmath mbstring intl zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && printf '<Directory %s>\n    AllowOverride All\n    Require all granted\n    FallbackResource /index.php\n</Directory>\n' \
       "$APACHE_DOCUMENT_ROOT" \
       > /etc/apache2/conf-available/app.conf \
    && a2enconf app

WORKDIR /var/www/html

COPY . /var/www/html

RUN if [ -f composer.json ]; then \
        composer install --no-interaction --prefer-dist; \
    fi \
    && mkdir -p storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
