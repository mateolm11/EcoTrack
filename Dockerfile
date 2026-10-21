FROM php:8.2-apache

# Extensión PDO para MySQL
RUN docker-php-ext-install pdo_mysql

# ServerName para evitar advertencias
RUN grep -q "ServerName localhost" /etc/apache2/apache2.conf || echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/

# Permisos de escritura en api/ para avisos.log
RUN chown -R www-data:www-data /var/www/html/api

# Entrypoint en tiempo de ejecución: resuelve de raíz cualquier MPM conflictivo y aplica el puerto dinámico de Railway
RUN printf '#!/bin/sh\n\
set -e\n\
a2dismod -f mpm_event 2>/dev/null || true\n\
a2dismod -f mpm_worker 2>/dev/null || true\n\
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true\n\
a2enmod mpm_prefork\n\
sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf\n\
sed -i "s/:80/:${PORT:-80}/g" /etc/apache2/sites-available/*.conf\n\
exec apache2-foreground "$@"\n' > /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
