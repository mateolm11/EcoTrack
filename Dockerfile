FROM php:8.2-apache

# Extensión PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# Asegurar un único MPM activo en Apache y definir ServerName
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Apache escucha en el puerto que indique el proveedor (variable PORT); por defecto 80
ENV PORT=80
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

# Permisos de escritura en api/ para avisos.log
RUN chown -R www-data:www-data /var/www/html/api
