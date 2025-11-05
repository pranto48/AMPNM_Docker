#!/bin/bash
set -e

# This script is executed when the Docker container starts.

# 1. Modify Apache's configuration to listen on port 2266 instead of the default 80.
echo "Configuring Apache to listen on port 2266..."
sed -i 's/Listen 80/Listen 2266/g' /etc/apache2/ports.conf
sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:2266>/g' /etc/apache2/sites-available/000-default.conf

# 2. Start the Apache web server.
# 'exec' is used to replace the script process with the Apache process,
# which is standard practice for container entrypoints.
echo "Starting Apache web server..."
exec apache2-foreground