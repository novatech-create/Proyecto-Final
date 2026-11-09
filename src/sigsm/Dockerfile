FROM php:8.5-apache-bookworm

RUN docker-php-ext-install pdo_mysql \
    && php -r 'exit(extension_loaded("mbstring") && extension_loaded("fileinfo") ? 0 : 1);' \
    && a2enmod rewrite

COPY docker/sigsm.conf /etc/apache2/conf-enabled/sigsm.conf
COPY docker/subidas.ini /usr/local/etc/php/conf.d/sigsm-subidas.ini

WORKDIR /var/www/html/sigsm
COPY .htaccess ./
COPY api/ api/
COPY datos/ datos/
COPY negocio/ negocio/
COPY presentacion/ presentacion/
COPY Archivos/.htaccess Archivos/.htaccess
RUN chown www-data:www-data Archivos
