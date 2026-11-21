# Production runs PHP 8.3 behind Apache; E2E uses the same minor version.
FROM php:8.3-apache
RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers \
    && mkdir -p /var/log/golf-el-faro && chown www-data:www-data /var/log/golf-el-faro
COPY apache.conf /etc/apache2/conf-enabled/e2e.conf
