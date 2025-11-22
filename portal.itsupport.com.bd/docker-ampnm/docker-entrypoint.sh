#!/usr/bin/env bash
set -euo pipefail

APACHE_PORT="${APACHE_PORT:-2266}"

echo "Configuring Apache to listen on port ${APACHE_PORT}..."
sed -ri "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf || true
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf || true

echo "Starting Apache web server on port ${APACHE_PORT}..."
exec apache2-foreground
