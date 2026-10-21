FROM php:8.2-apache

# Extensión PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# Asegurar un único MPM activo en Apache y definir ServerName
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/

# Permisos de escritura en api/ para avisos.log
RUN chown -R www-data:www-data /var/www/html/api

# Configurar el puerto dinámico de Railway al iniciar el contenedor y lanzar Apache
CMD sh -c "sed -i \"s/Listen 80/Listen \${PORT:-80}/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:\${PORT:-80}/g\" /etc/apache2/sites-available/*.conf && apache2-foreground"
