FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM php:8.3-cli-bookworm AS app

WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8000 \
    EXTRACTION_BIND_HOST=127.0.0.1 \
    EXTRACTION_BIND_PORT=8001 \
    EXTRACTION_SERVICE_WORKERS=4 \
    QUEUE_NAMES=default \
    QUEUE_SLEEP=3 \
    QUEUE_TIMEOUT=0 \
    QUEUE_TRIES=3 \
    QUEUE_WORKER_PROCESSES=4 \
    CONTAINER_ROLE=all

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        supervisor \
        python3 \
        python3-pip \
        python3-venv \
        unzip \
        libgomp1 \
        libgl1 \
        libglib2.0-0 \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libsqlite3-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN python3 -m venv /opt/venv

ENV PATH="/opt/venv/bin:${PATH}"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --no-interaction

RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r services/extraction-service/requirements.txt

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwx storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["entrypoint"]
CMD []