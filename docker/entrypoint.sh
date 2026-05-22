#!/bin/sh
set -e

# Jalankan migrasi database secara otomatis di server produksi (hanya sekali)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Jalankan command utama yang dikirim dari Coolify (Web, Horizon, atau Reverb)
exec "$@"
