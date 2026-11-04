FROM dunglas/frankenphp:php8.4

# Instala curl para os healthchecks e extensões do Laravel
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    pdo_mysql \
    pdo_sqlite \
    bcmath \
    mbstring \
    intl \
    zip

# Copia o Composer para o container
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configura o FrankenPHP para HTTP na porta 80
# e define a pasta pública do Laravel
ENV SERVER_NAME=:80
ENV SERVER_ROOT=/app/public

WORKDIR /app

# Copia os arquivos do Laravel
COPY . /app

# Instala dependências e configura permissões
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
