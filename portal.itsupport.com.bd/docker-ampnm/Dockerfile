# Use the official PHP Apache image as the base
FROM php:8.2-apache

# Install system dependencies, including ping utility and mysql-client
RUN apt-get update && apt-get install -y \
    iputils-ping \
    default-mysql-client \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    wget \
    git \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Copy and set permissions for the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions for web server
RUN chown -R www-data:www-data /var/www/html

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]