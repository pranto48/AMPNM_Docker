FROM php:8.2-apache

# Install system dependencies required for PHP extensions and runtime tooling
RUN apt-get update \
    && apt-get install -y \
        curl \
        git \
        nmap \
        unzip \
        default-mysql-client \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Build the required PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        exif \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip

# Enable Apache's mod_rewrite for pretty URLs
RUN a2enmod rewrite

# Copy application code into the image
COPY . /var/www/html/

# Copy entrypoint script and ensure it is executable
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Ensure application files are owned by the Apache user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Prepare writable upload directories
RUN mkdir -p /var/www/html/uploads/icons \
    && mkdir -p /var/www/html/uploads/map_backgrounds \
    && mkdir -p /var/www/html/uploads/backups \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# Expose the application port
EXPOSE 2266

# Use the custom entrypoint to adjust Apache configuration before start
ENTRYPOINT ["/docker-entrypoint.sh"]
