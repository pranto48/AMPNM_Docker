FROM php:8.2-apache

# Install system dependencies
# Adding a dummy argument to force cache invalidation for this step
ARG CACHE_BREAKER=3
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
    mariadb-client-compat \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# Using explicit /usr paths for gd dependencies
RUN docker-php-ext-configure gd --with-freetype=/usr --with-jpeg=/usr --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip intl opcache bcmath exif curl

# Enable Apache modules
RUN a2enmod rewrite

# Copy application files from the current build context
COPY . /var/www/html/

# Copy the entrypoint script to the root directory and make it executable
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 2266 (or whatever port your app runs on)
EXPOSE 2266

# Update Apache configuration to listen on 2266
RUN echo "Listen 2266" >> /etc/apache2/ports.conf \
    && sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-available/000-default.conf \
    && sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-enabled/000-default.conf

# Ensure the uploads directory exists and has correct permissions
RUN mkdir -p /var/www/html/uploads/icons \
    mkdir -p /var/www/html/uploads/map_backgrounds \
    mkdir -p /var/www/html/uploads/backups \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# Use the copied entrypoint script from the root
ENTRYPOINT ["/docker-entrypoint.sh"]