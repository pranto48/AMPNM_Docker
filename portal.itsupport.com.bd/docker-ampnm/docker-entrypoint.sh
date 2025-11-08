#!/bin/bash

# Wait for MySQL to be ready
/usr/local/bin/wait-for-it.sh db:3306 --timeout=60 --strict -- echo "MySQL is up and running!"

# Run database setup script if not already done
php /var/www/html/license_setup.php # Changed to license_setup.php as it's the main setup for the portal

# Set permissions for uploads directory (now that the volume is mounted)
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Start Apache web server
apache2-foreground