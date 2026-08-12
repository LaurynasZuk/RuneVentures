FROM php:8.3-apache

ARG NODE_VERSION=22.18.0

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    VITE_APP_NAME=RuneVentures

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libpq-dev \
        unzip \
        xz-utils \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN ARCH="$(dpkg --print-architecture)" \
    && case "$ARCH" in \
        amd64) NODE_ARCH="x64" ;; \
        arm64) NODE_ARCH="arm64" ;; \
        *) echo "Unsupported architecture: $ARCH" && exit 1 ;; \
    esac \
    && curl -fsSLO "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" \
    && tar -xJf "node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" -C /usr/local --strip-components=1 \
    && rm "node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" \
    && node --version \
    && npm --version

WORKDIR /var/www/html

COPY . .

# Keep build phases separate so Render reports the exact failing step.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

RUN php artisan route:clear
RUN npm ci
RUN npm run build

RUN rm -rf node_modules \
    && npm cache clean --force \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/runventures.conf \
    && a2enconf runventures

COPY docker/render-start.sh /usr/local/bin/render-start
RUN chmod +x /usr/local/bin/render-start

EXPOSE 10000

CMD ["render-start"]
