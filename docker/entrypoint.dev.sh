#!/bin/sh
set -e

# ============================================================
# MCPHost Dev Entrypoint
# ============================================================

# Pastikan dependencies PHP terinstal di dalam volume lokal
if [ ! -d "vendor" ]; then
    echo "[entrypoint] Installing PHP dependencies..."
    composer install --no-interaction
fi

# Pastikan dependencies Bun terinstal
if [ ! -d "node_modules" ]; then
    echo "[entrypoint] Installing Bun dependencies..."
    bun install
fi

# --- WAJIB di WSL2/Docker: Build Laravel application caches ---
# Di WSL2, baca banyak file kecil (config/, routes/) sangat lambat (~5-10 detik/request).
# config:cache + route:cache menggabungkan semua file menjadi SATU file → baca instan.
# OPcache kemudian meng-compile file tunggal itu → subsequent requests ~61ms.
#
# Kapan perlu refresh cache setelah perubahan:
#   - Ubah .env            → bun run docker:clear && bun run docker:optimize
#   - Ubah config/*.php    → bun run docker:clear && bun run docker:optimize
#   - Ubah routes/*.php    → bun run docker:clear && bun run docker:optimize
#   - Ubah file .php lain  → TIDAK PERLU (OPcache auto-detect via VALIDATE_TIMESTAMPS=1)
echo "[entrypoint] Building application caches (config + route + event)..."
php artisan optimize --quiet 2>/dev/null || true

# Jalankan migrasi database secara otomatis
echo "[entrypoint] Running database migrations..."
php artisan migrate --force

# Jalankan seeder secara otomatis hanya jika tabel user masih kosong (fresh database)
# Hal ini mencegah truncation dari server MCP kustom milik developer jika seeder dijalankan ulang pada kontainer restart.
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null | grep -o '[0-9]\+' | head -n1) || USER_COUNT=0
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "[entrypoint] Database appears to be fresh (0 users). Seeding default database records..."
    php artisan db:seed --force
else
    echo "[entrypoint] Database already has data ($USER_COUNT users). Skipping database seeding to preserve custom records."
fi

# Serahkan kontrol ke command utama
if [ "$1" = "/init" ] || [ "$1" = "php-fpm-nginx" ]; then
    # Panggil entrypoint bawaan serversideup untuk melakukan rendering config & setup
    exec docker-php-serversideup-entrypoint "$@"
else
    # Jalankan CLI command secara langsung (artisan queue, reverb, dll.)
    exec "$@"
fi
