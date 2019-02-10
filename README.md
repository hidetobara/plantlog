This is Plantlog project.
It can watch and alanyze many plants' logs.

* setup
 - docker-compose build
 - docker-compose up -d
 - docker-compose exec web /bin/bash

 - docker-compose down

* deploy
 - composer install
 - php artisan migrate
 - php artisan cache:clear
 - php artisan config:clear
 - php artisan route:clear
 - php artisan view:clear

* apache2
NOTICE: a2enmod proxy_fcgi setenvif
NOTICE: a2enconf php7.2-fpm
