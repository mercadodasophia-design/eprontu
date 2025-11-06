# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install required extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache for Cloud Run
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN a2enmod rewrite
RUN a2enmod headers

# Create .htaccess for URL rewriting
RUN echo "RewriteEngine On" > .htaccess \
    && echo "RewriteCond %{REQUEST_FILENAME} !-f" >> .htaccess \
    && echo "RewriteCond %{REQUEST_FILENAME} !-d" >> .htaccess \
    && echo "RewriteRule ^(.*)$ index.php [QSA,L]" >> .htaccess

# Expose port 8080 (Cloud Run requirement)
EXPOSE 8080

# Configure Apache to run on port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

# Start Apache
CMD ["apache2-foreground"]
