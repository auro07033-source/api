FROM php:8.2-apache
COPY . /var/www/html/
RUN echo "DirectoryIndex ibancozumle.php index.php" > /etc/apache2/conf-available/dir.conf && a2enconf dir
EXPOSE 80
