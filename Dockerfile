FROM php:8.2-apache

# Extensión PDO para MySQL (cURL ya viene incluido en esta imagen)
RUN docker-php-ext-install pdo_mysql

# Apache escucha en el puerto dinámico que indique el proveedor (variable PORT); por defecto 80
ENV PORT=80
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

# Permisos de escritura en api/ para logs en modo simulación
RUN chown -R www-data:www-data /var/www/html/api
