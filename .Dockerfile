# Usa la imagen oficial de PHP con Apache
FROM php:8.1-apache

# Copia tu código a la carpeta del servidor
COPY . /var/www/html/

# Cambia el DocumentRoot de Apache a 'public/contraseña'
RUN sed -i 's|/var/www/html|/var/www/html/public/contraseña|g' /etc/apache2/sites-available/000-default.conf

# Da permisos
RUN chown -R www-data:www-data /var/www/html

# Habilita mod_rewrite si usas rutas amigables (opcional)
RUN a2enmod rewrite
