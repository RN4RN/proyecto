FROM php:8.1-apache

# Instalar las extensiones necesarias
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY . /var/www/html/

# Otros comandos que ya tengas (como cambiar permisos, habilitar mod_rewrite, etc)
RUN sed -i 's|/var/www/html|/var/www/html/public/contraseña|g' /etc/apache2/sites-available/000-default.conf
RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

# Comando por defecto
CMD ["apache2-foreground"]
