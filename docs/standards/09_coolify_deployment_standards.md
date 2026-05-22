# Coolify Production Deployment Standards

Dokumen ini mendefinisikan standar struktur direktori, konfigurasi Docker, dan topologi deployment untuk meluncurkan aplikasi **Laravel 13 + Vue/Inertia + Horizon + Reverb** ke server **Coolify** secara mulus (*seamless*) menggunakan **Coolify GitHub App**.

---

## 1. Topologi Deployment Coolify (Rekomendasi Utama)

Untuk aplikasi skala produksi yang tangguh, kita menggunakan pola **Multi-Resource dari Repositori yang Sama** (bukan menggabungkan semuanya ke dalam satu kontainer raksasa). Dengan topologi ini, restarter web tidak akan memutuskan koneksi WebSocket aktif atau menggagalkan tugas latar belakang (*queue jobs*).

Kita mendaftarkan **3 Resource terpisah di Coolify** yang merujuk pada repositori GitHub yang sama:

```
                            ┌──────────────────────────────────┐
                            │      COOLIFY GITHUB APP LINK     │
                            └────────────────┬─────────────────┘
                                             │
         ┌───────────────────────────────────┼───────────────────────────────────┐
         ▼                                   ▼                                   ▼
┌──────────────────┐                ┌──────────────────┐                ┌──────────────────┐
│ 1. WEB RESOURCE  │                │2. HORIZON WORKER │                │3. REVERB SERVER  │
│ - Port: 80 / 443 │                │ - Port: None     │                │ - Port: 8080     │
│ - Command: Web   │                │ - Command:       │                │ - Command:       │
│   (Nginx/FPM)    │                │   `artisan horizon`│              │   `artisan reverb:start`│
└────────┬─────────┘                └────────┬─────────┘                └────────┬─────────┘
         │                                   │                                   │
         └───────────────────────────────────┼───────────────────────────────────┘
                                             ▼
                                ┌──────────────────────────┐
                                │     SHARED DATABASES     │
                                │ (PostgreSQL / Redis / S3)│
                                └──────────────────────────┘
```

---

## 2. Standar Struktur Folder Deployment (`docker/`)

Untuk mendukung proses *build* di Coolify, kita membuat folder **`docker/`** khusus di root proyek untuk menyimpan semua instruksi DevOps:

```
mcphost/
├── app/
├── ...
├── docker/
│   ├── Dockerfile             # Multi-stage Docker build untuk Production
│   ├── supervisord.conf       # Konfigurasi proses jika memilih opsi single-container
│   └── entrypoint.sh          # Script inisialisasi boot (migration, caching)
├── docker-compose.prod.yml    # Konfigurasi orkestrasi untuk local staging
└── pint.json
```

---

## 3. Spesifikasi File DevOps

### A. Dockerfile Produksi (`docker/Dockerfile`)
Kita menggunakan teknik **Multi-stage Build** untuk memastikan image akhir berukuran sangat kecil, aman, dan tidak mengandung modul development Node.js:

```dockerfile
# === STAGE 1: Build Frontend Assets (Vue 3 / Inertia) ===
FROM oven/bun:1-alpine AS assets-builder
WORKDIR /app
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY . .
RUN bun run build

# === STAGE 2: Production PHP Runtime ===
FROM serversideup/php:8.3-fpm-nginx AS production
WORKDIR /var/www/html

# Ganti user ke root untuk konfigurasi sistem
USER root

# Salin source code PHP dan aset yang sudah di-compile dari Stage 1
COPY --chown=webwrite:webwrite . .
COPY --from=assets-builder --chown=webwrite:webwrite /app/public/build ./public/build

# Pindah kembali ke user aman non-root bawaan image
USER webwrite

# Install dependensi PHP untuk produksi
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set up konfigurasi caching Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Jalankan entrypoint script
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
```

### B. Entrypoint Script (`docker/entrypoint.sh`)
Script ini menjamin database dan konfigurasi Laravel bermigrasi dengan aman sebelum kontainer mulai melayani request:

```bash
#!/bin/sh
set -e

# Jalankan migrasi database secara otomatis di server produksi (hanya sekali)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Jalankan command utama yang dikirim dari Coolify (Web, Horizon, atau Reverb)
exec "$@"
```

---

## 4. Konfigurasi 3 Resource di Dashboard Coolify

Saat mendaftarkan proyek Anda di Coolify, buat 3 resource dari repositori GitHub Anda dan konfigurasikan bagian **Alternative Start Command** masing-masing sebagai berikut:

### 1. Resource Web (Aplikasi Utama)
*   **Domain:** `https://analitik.pemerintah.go.id`
*   **Base Directory:** `/`
*   **Install Command:** `Default`
*   **Start Command:** `web` (bawaan dari base image serversideup untuk menyalakan Nginx + PHP-FPM)
*   **Environment Variable Tambahan:**
    *   `RUN_MIGRATIONS=true` (Hanya diaktifkan di Resource Web agar migrasi tidak bertabrakan)

### 2. Resource Horizon (Queue Worker)
*   **Domain:** *Kosongkan* (Horizon worker tidak membutuhkan akses domain publik)
*   **Base Directory:** `/`
*   **Start Command:** `php artisan horizon`
*   **Environment Variable Tambahan:**
    *   `RUN_MIGRATIONS=false`

### 3. Resource Reverb (WebSocket Server)
*   **Domain:** `https://ws.analitik.pemerintah.go.id` (Tunjuk subdomain khusus untuk WebSockets)
*   **Base Directory:** `/`
*   **Start Command:** `php artisan reverb:start --port=8080`
*   **Port Mapping di Coolify:** Hubungkan Port Internal `8080` ke Publik Port `80/443`
*   **Environment Variable Tambahan:**
    *   `RUN_MIGRATIONS=false`
    *   `REVERB_SCHEME=https`
    *   `REVERB_HOST=ws.analitik.pemerintah.go.id`

---

## 5. Keuntungan Struktur Ini untuk Coolify
*   **Zero-Downtime Deployment:** Coolify menggunakan teknik rolling update. Kontainer baru akan dibangun terlebih dahulu, dipastikan sehat (*healthy*), baru kontainer lama dimatikan.
*   **Keamanan Terjaga:** Menghindari penggunaan user `root` di kontainer produksi.
*   **Kemudahan Caching:** Aset frontend Vue 3 yang telah di-*build* langsung menyatu di dalam kontainer Web, menyajikan performa loading yang instan untuk antarmuka chat sekelas Gemini.
