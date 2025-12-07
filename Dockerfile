# Use official PHP image with Apache
FROM php:8.3-apache

# Install system dependencies and SQLite extension
RUN apt-get update \
	&& apt-get install -y --no-install-recommends libsqlite3-dev \
	&& docker-php-ext-install pdo pdo_sqlite \
	&& rm -rf /var/lib/apt/lists/*

# Configure Apache to serve from the public directory and enable URL rewriting
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/default-ssl.conf \
	&& printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
	&& a2enconf servername \
	&& a2enmod rewrite

# Set working directory and copy project files
WORKDIR /var/www/html
COPY . /var/www/html

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
