# --- API Symfony servie par Apache + PHP ---
FROM php:8.3-apache

# Extensions PHP nécessaires (PostgreSQL, intl, opcache)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql intl opcache zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# VirtualHost Apache (docroot -> public/, routing via FallbackResource)
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# 1) Dépendances (couche cache séparée)
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# 2) Code applicatif
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var \
    && chown -R www-data:www-data var

# Entrypoint : attend la base, met à jour le schéma, vide le cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
