#!/bin/bash

# Wait for MySQL to be ready
/usr/local/bin/wait-for-it.sh db:3306 --timeout=60 --strict -- echo "MySQL is up and running!"

# Run database setup script if not already done
php /var/www/html/database_setup.php

# Apache configuration is now handled in the Dockerfile,
# so these lines are removed from the entrypoint to prevent conflicts.
# echo "Listen 2266" >> /etc/apache2/ports.conf
# sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-available/000-default.conf
# sed -i -e 's/VirtualHost \*:80/VirtualHost \*:2266/g' /etc/apache2/sites-enabled/000-default.conf

# Set permissions for uploads directory
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Start Apache web server
apache2-foreground