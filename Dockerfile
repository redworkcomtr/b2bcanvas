FROM almalinux:9

RUN dnf -y update \
    && dnf -y install epel-release dnf-plugins-core \
    && dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm \
    && dnf -y module reset php \
    && dnf -y module enable php:remi-8.3 \
    && dnf -y module enable nodejs:20 \
    && dnf -y install \
        git \
        curl \
        unzip \
        make \
        gcc \
        gcc-c++ \
        patch \
        python3 \
        nodejs \
        npm \
        php-cli \
        php-pdo \
        php-pgsql \
        php-sqlite3 \
        php-zip \
        php-redis \
    && dnf clean all \
    && rm -rf /var/cache/dnf

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json composer.lock package*.json ./
COPY . .
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist \
    && if [ -f package-lock.json ]; then npm ci --silent; fi \
    && if [ -f package.json ]; then npm run build --silent; fi \
    && composer dump-autoload --classmap-authoritative

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
