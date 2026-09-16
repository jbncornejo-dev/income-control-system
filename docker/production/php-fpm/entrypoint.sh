#!/bin/sh
set -e

# Initialize storage directory if empty
if [ ! "$(ls -A /var/www/storage)" ]; then
  echo "Initializing storage directory..."
  cp -R /var/www/storage-init/. /var/www/storage
fi

# 1. Crear las carpetas obligatorias de Laravel que Git ignora
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/bootstrap/cache

# 2. Dar permisos al usuario www-data (el que corre PHP y Apache)
chown -R www-data:www-data /var/www/storage
chown -R www-data:www-data /var/www/bootstrap/cache

# Remove storage-init directory
rm -rf /var/www/storage-init

# Run Laravel migrations
php artisan migrate --force

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run the default command
exec "$@"