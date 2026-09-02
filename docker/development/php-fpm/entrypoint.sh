#!/bin/sh
set -e

# Clear configurations to avoid caching issues in development
# Only run if dependencies are installed, so the container can still start on a fresh clone
if [ -f vendor/autoload.php ]; then
    echo "Clearing configurations..."
    php artisan config:clear
    php artisan route:clear
fi

# Run the default command (e.g., php-fpm or bash)
exec "$@"
