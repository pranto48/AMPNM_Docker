FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    nmap \
    curl \
    libcurl4-openssl-dev \
    mariadb-client-compat \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Download and install wait-for-it.sh
ADD https://github.com/vishnubob/wait-for-it/raw/master/wait-for-it.sh /usr/local/bin/wait-for-it.sh
RUN chmod +x /usr/local/bin/wait-for-it.sh

# Install PHP extensions in separate steps for stability
RUN docker-php-ext-install gd
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install intl
RUN docker-php-ext-install opcache
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install exif
RUN docker-php-ext-install curl

# Apache Configuration
# Disable default site and enable rewrite module
RUN a2dissite 000-default.conf
RUN a2enmod rewrite

# Create a new Apache virtual host configuration for port 2266
RUN echo "<VirtualHost *:2266>\n\tDocumentRoot /var/www/html\n\t<Directory /var/www/html>\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n\tErrorLog \${APACHE_LOG_DIR}/error.log\n\tCustomLog \${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>" > /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default.conf

# Update ports.conf to listen only on 2266
RUN echo "Listen 2266" > /etc/apache2/ports.conf

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ensure the uploads directory exists in the image (will be overlaid by volume if host has it)
# Permissions will be set by the entrypoint script on the mounted volume.
RUN mkdir -p /var/www/html/uploads/icons \
    mkdir -p /var/www/html/uploads/map_backgrounds \
    mkdir -p /var/www/html/uploads/backups

# Expose port 2266
EXPOSE 2266

# The entrypoint script will handle starting Apache
CMD ["apache2-foreground"]