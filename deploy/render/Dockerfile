FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev supervisor ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql gd intl zip mbstring bcmath calendar pcntl opcache \
    && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts \
    && composer check-platform-reqs --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/render/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/render/php.ini /usr/local/etc/php/conf.d/render.ini
COPY deploy/render/mpm.conf /etc/apache2/mods-available/mpm_prefork.conf
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr SESSION_DRIVER=database SESSION_SECURE_COOKIE=true CACHE_DRIVER=file QUEUE_CONNECTION=database
EXPOSE 10000
CMD ["bash", "deploy/render/start.sh"]
