FROM php:7.2-apache

RUN apt-get update \
    && apt-get install -y zip unzip vim libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql \
    && gnupg2

COPY php-base.ini $PHP_INI_DIR/conf.d/

COPY httpd-base.conf /etc/apache2/sites-available/laravel.conf
RUN a2dissite 000-default.conf && a2ensite laravel.conf && a2enmod rewrite
RUN ln -s /project/site/public /var/www/html/plant

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash - && apt-get install nodejs
