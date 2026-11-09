FROM dunglas/frankenphp:php8.5-bookworm

RUN install-php-extensions pdo_pgsql pdo_mysql intl zip bcmath opcache

# The binary ships with a capability for binding ports below 1024. Hosts such as
# Render refuse to run binaries that carry it, and this app listens on 8080.
RUN setcap -r /usr/local/bin/frankenphp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install dependencies first so this layer is cached between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# Runs package discovery and publishes Filament's assets.
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/start.sh

EXPOSE 8080

CMD ["docker/start.sh"]
