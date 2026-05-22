# === STAGE 1: Build Frontend Assets (Vue 3 / Inertia) ===
FROM node:20-alpine AS assets-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# === STAGE 2: Production PHP Runtime ===
FROM serversideup/php:8.3-fpm-nginx AS production
WORKDIR /var/www/html

# Ganti user ke root untuk konfigurasi sistem
USER root

# Salin source code PHP dan aset yang sudah di-compile dari Stage 1
COPY --chown=www-data:www-data . .
COPY --from=assets-builder --chown=www-data:www-data /app/public/build ./public/build

# Pindah kembali ke user aman non-root bawaan image
USER www-data

# Install dependensi PHP untuk produksi
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set up konfigurasi caching Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Jalankan entrypoint script
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
