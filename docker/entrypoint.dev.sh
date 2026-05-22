#!/bin/sh
set -e

# Pastikan dependencies PHP terinstal di dalam volume lokal
if [ ! -d "vendor" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-interaction
fi

# Pastikan dependencies NPM terinstal
if [ ! -d "node_modules" ]; then
    echo "Installing NPM dependencies..."
    npm install
fi

# Jalankan migrasi database secara otomatis
echo "Running database migrations..."
php artisan migrate --force

# Serahkan kontrol ke command utama
if [ "$1" = "/init" ] || [ "$1" = "php-fpm-nginx" ]; then
    # Panggil entrypoint bawaan serversideup untuk melakukan rendering config & setup
    exec docker-php-serversideup-entrypoint "$@"
else
    # Jalankan CLI command secara langsung (artisan queue, reverb, dll.)
    exec "$@"
fi
