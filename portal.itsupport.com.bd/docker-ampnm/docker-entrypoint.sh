#!/usr/bin/env bash
set -euo pipefail

# Adjust Apache to listen on port 2266 before starting the service.
echo "Configuring Apache to listen on port 2266..."
sed -i 's/Listen 80/Listen 2266/g' /etc/apache2/ports.conf
sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:2266>/g' /etc/apache2/sites-available/000-default.conf

# Start Apache in the foreground.
echo "Starting Apache web server..."
exec apache2-foreground
