FROM php:8.2-apache

# Instalar extensión PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# Evitar crash de Apache ("More than one MPM loaded") y warning de ServerName
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copiar el proyecto al directorio público de Apache
COPY . /var/www/html/

# Permisos de escritura en api/ para logs en modo simulación
RUN chown -R www-data:www-data /var/www/html/api

# Configurar el puerto dinámico de Railway al iniciar el contenedor y lanzar Apache
CMD sh -c "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"
