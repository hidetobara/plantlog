FROM php:7.2-apache

RUN apt-get update \
    && apt-get install -y zip unzip vim libpq-dev less curl \
    && docker-php-ext-install pdo_mysql

COPY php-base.ini $PHP_INI_DIR/conf.d/
COPY httpd-base.conf /etc/apache2/sites-available/laravel.conf
RUN a2dissite 000-default.conf && a2ensite laravel.conf && a2enmod rewrite
RUN ln -s /project/public /var/www/plant

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

RUN apt-get install -my wget gnupg \
    && curl -sL https://deb.nodesource.com/setup_10.x | bash - && apt-get update && apt-get install -y nodejs
