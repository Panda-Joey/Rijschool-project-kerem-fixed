FROM php:8.3-apache
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Zorg dat Apache altijd index.php pakt en .htaccess mag gebruiken (voorkomt 403 Forbidden op /)
RUN a2enmod rewrite
COPY ./apache/rijschool.conf /etc/apache2/conf-available/rijschool.conf
RUN a2enconf rijschool
