#!/bin/bash

# ============================================================
# MCPHost Dev Tools
# Usage: bash scripts/dev-tools.sh <command>
# ============================================================

set -e

COMPOSE="docker compose"
WEB="$COMPOSE exec web"
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
NC="\033[0m" # No Color

# --- Helper ---
info()    { echo -e "${CYAN}[dev-tools]${NC} $1"; }
success() { echo -e "${GREEN}[dev-tools]${NC} ✅ $1"; }
warn()    { echo -e "${YELLOW}[dev-tools]${NC} ⚠️  $1"; }
error()   { echo -e "${RED}[dev-tools]${NC} ❌ $1"; exit 1; }

usage() {
    echo -e "\n${BOLD}MCPHost Dev Tools${NC}"
    echo -e "Usage: ${CYAN}bash scripts/dev-tools.sh <command>${NC}\n"
    echo -e "${BOLD}Cache Management:${NC}"
    echo -e "  ${GREEN}clear${NC}         Hapus semua cache (config, route, view, opcache)"
    echo -e "  ${GREEN}optimize${NC}      Build semua cache (untuk simulasi production speed)"
    echo -e "  ${GREEN}refresh${NC}       Clear + jalankan ulang migrasi"
    echo ""
    echo -e "${BOLD}Database:${NC}"
    echo -e "  ${GREEN}migrate${NC}       Jalankan migrasi"
    echo -e "  ${GREEN}fresh${NC}         Reset DB + migrate + seed"
    echo -e "  ${GREEN}seed${NC}          Jalankan seeder"
    echo ""
    echo -e "${BOLD}Diagnostics:${NC}"
    echo -e "  ${GREEN}status${NC}        Status semua container + OPcache info"
    echo -e "  ${GREEN}bench${NC}         Benchmark kecepatan HTTP (5 request)"
    echo -e "  ${GREEN}profile${NC}       Profile bootstrap Laravel"
    echo -e "  ${GREEN}logs${NC}          Tail logs semua container"
    echo ""
    echo -e "${BOLD}Artisan Shortcuts:${NC}"
    echo -e "  ${GREEN}tinker${NC}        Buka Laravel Tinker"
    echo -e "  ${GREEN}queue${NC}         Monitor queue jobs"
    echo -e "  ${GREEN}routes${NC}        List semua routes"
    echo ""
}

# --- Commands ---

cmd_clear() {
    info "Menghapus semua Laravel cache..."
    $WEB php artisan optimize:clear
    info "Merestart FPM workers agar state bersih..."
    $COMPOSE restart web
    success "Cache dihapus + FPM workers direstart. Perubahan config/route langsung aktif."
}

cmd_optimize() {
    info "Membangun semua cache (config + route + event + views)..."
    $WEB php artisan optimize
    info "Merestart FPM workers agar menggunakan cache baru..."
    $COMPOSE restart web
    success "Cache dibangun + FPM workers direstart. Server siap dengan performa penuh!"
}

cmd_refresh() {
    info "Refresh: rebuild cache + migrate + restart workers..."
    $WEB php artisan optimize:clear
    $WEB php artisan optimize
    $WEB php artisan migrate --force
    $COMPOSE restart web
    success "Refresh selesai. FPM workers bersih dan cache aktif."
}

cmd_migrate() {
    info "Menjalankan migrasi..."
    $WEB php artisan migrate --force
    success "Migrasi selesai."
}

cmd_fresh() {
    warn "Ini akan MENGHAPUS semua data di database!"
    read -r -p "Lanjutkan? (y/N) " confirm
    [[ "$confirm" =~ ^[Yy]$ ]] || { info "Dibatalkan."; exit 0; }
    info "Reset database + migrate + seed..."
    $WEB php artisan migrate:fresh --seed --force
    success "Database fresh + seeded."
}

cmd_seed() {
    info "Menjalankan seeder..."
    $WEB php artisan db:seed --force
    success "Seeding selesai."
}

cmd_status() {
    echo -e "\n${BOLD}=== Container Status ===${NC}"
    docker compose ps

    echo -e "\n${BOLD}=== OPcache Status ===${NC}"
    $WEB php -r "
        if (!function_exists('opcache_get_status')) { echo '  ❌ OPcache not loaded\n'; exit; }
        \$s = opcache_get_status(false);
        if (!\$s || !\$s['opcache_enabled']) { echo '  ❌ OPcache DISABLED\n'; exit; }
        echo '  ✅ OPcache ENABLED\n';
        echo '  Scripts cached : ' . \$s['opcache_statistics']['num_cached_scripts'] . '\n';
        echo '  Memory used    : ' . round(\$s['memory_usage']['used_memory']/1024/1024, 1) . ' MB\n';
        echo '  Memory free    : ' . round(\$s['memory_usage']['free_memory']/1024/1024, 1) . ' MB\n';
        \$jit = \$s['jit'] ?? null;
        echo '  JIT enabled    : ' . (\$jit && \$jit['enabled'] ? '✅ YES' : '❌ NO') . '\n';
    " 2>/dev/null || warn "Tidak bisa cek OPcache via CLI (shared FPM memory berbeda dengan CLI)"

    echo -e "\n${BOLD}=== Active Caches ===${NC}"
    $WEB sh -c "
        [ -f bootstrap/cache/config.php ] && echo '  ✅ config:cache aktif' || echo '  ○  config cache: tidak aktif (dev mode)';
        [ -f bootstrap/cache/routes-v7.php ] && echo '  ✅ route:cache aktif' || echo '  ○  route cache: tidak aktif (dev mode)';
        [ -f bootstrap/cache/events.php ] && echo '  ✅ event:cache aktif' || echo '  ○  event cache: tidak aktif (dev mode)';
    "
    echo ""
}

cmd_bench() {
    info "Menjalankan benchmark 7 request ke http://127.0.0.1:8900 ..."
    echo ""
    for i in 1 2 3 4 5 6 7; do
        RESULT=$(curl -s -o /dev/null -w "TTFB: %{time_starttransfer}s | Total: %{time_total}s | HTTP: %{http_code}" http://127.0.0.1:8900/ 2>/dev/null)
        echo "  Request $i: $RESULT"
    done
    echo ""
    success "Benchmark selesai. Request ke-2+ harus < 200ms (FPM worker warm)."
}

cmd_profile() {
    info "Menjalankan Laravel boot profiler..."
    $WEB php scratch-profile.php 2>/dev/null || warn "scratch-profile.php tidak ditemukan di root project."
}

cmd_logs() {
    info "Menampilkan logs semua container (Ctrl+C untuk berhenti)..."
    docker compose logs -f --tail=50
}

cmd_tinker() {
    info "Membuka Laravel Tinker..."
    $WEB php artisan tinker
}

cmd_queue() {
    info "Memonitor queue jobs..."
    $WEB php artisan queue:monitor
}

cmd_routes() {
    info "Menampilkan semua routes..."
    $WEB php artisan route:list
}

# --- Entry point ---
CMD="${1:-help}"

case "$CMD" in
    clear)    cmd_clear ;;
    optimize) cmd_optimize ;;
    refresh)  cmd_refresh ;;
    migrate)  cmd_migrate ;;
    fresh)    cmd_fresh ;;
    seed)     cmd_seed ;;
    status)   cmd_status ;;
    bench)    cmd_bench ;;
    profile)  cmd_profile ;;
    logs)     cmd_logs ;;
    tinker)   cmd_tinker ;;
    queue)    cmd_queue ;;
    routes)   cmd_routes ;;
    help|--help|-h|"") usage ;;
    *) error "Perintah tidak dikenal: '$CMD'. Jalankan tanpa argumen untuk melihat daftar perintah." ;;
esac
