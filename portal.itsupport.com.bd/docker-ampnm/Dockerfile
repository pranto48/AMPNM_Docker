FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    nmap \
    curl \
    mariadb-client-compat \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# Install common extensions first
RUN docker-php-ext-install -j$(nproc) pdo_mysql zip intl opcache bcmath exif curl \
    # Then install gd with its specific dependencies
    && docker-php-ext-install -j$(nproc) gd --with-freetype --with-jpeg

# Enable Apache modules
RUN a2enmod rewrite

# Copy application files from the current directory into the web root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 2266 (or whatever port your app runs on)
EXPOSE 2266

# Update Apache configuration to listen on 2266 and set ServerName directly in 000-default.conf
RUN echo "Listen 2266" >> /etc/apache2/ports.conf \
    && sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-available/000-default.conf \
    && sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-enabled/000-default.conf \
    && echo "ServerName localhost:2266" >> /etc/apache2/sites-available/000-default.conf

# Ensure the uploads directory exists and has correct permissions
RUN mkdir -p /var/www/html/uploads/icons \
    mkdir -p /var/www/html/uploads/map_backgrounds \
    mkdir -p /var/www/html/uploads/backups \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# The entrypoint script is now copied with the rest of the app files.
# We just need to ensure it's executable.
# The docker-compose.yml entrypoint will then execute it from /var/www/html/.
RUN chmod +x /var/www/html/docker-entrypoint.sh