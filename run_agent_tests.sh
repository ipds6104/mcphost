#!/bin/bash

# ==============================================================================
# BPS AI Agent Automated Testing & Performance Feedback Loop
# ==============================================================================

# ANSI Color Codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color
BOLD='\033[1m'

clear

echo -e "${CYAN}${BOLD}"
echo "================================================================================"
echo "    ____  ____  _____   ___     ____                 __                         "
echo "   / __ )/ __ \/ ___/  /   |   / __ \___  __________/ /___  ____  ____          "
echo "  / __  / /_/ /\__ \  / /| |  / /_/ / _ \/ ___/ ___/ / __ \/ __ \/ __ \         "
echo " / /_/ / ____/___/ / / ___ | / ____/  __/ /  / /__/ / /_/ / /_/ / /_/ /         "
echo "/_____/_/    /____/ /_/  |_|/_/    \___/_/   \___/_/\____/\____/ .___/          "
echo "                                                              /_/               "
echo "                 DATA ACCURACY & METRICS FEEDBACK LOOP                          "
echo "================================================================================"
echo -e "${NC}"

# 1. Cek status container docker
echo -e "🐳 ${BOLD}Memeriksa status kontainer docker...${NC}"
CONTAINER_STATUS=$(docker ps --filter "name=mcphost-web" --format "{{.Status}}")

if [ -z "$CONTAINER_STATUS" ]; then
    echo -e "${RED}❌ Kesalahan: Kontainer 'mcphost-web' tidak aktif!${NC}"
    echo -e "Silakan jalankan kontainer terlebih dahulu menggunakan: ${YELLOW}./start-dev.sh${NC}"
    exit 1
fi

echo -e "✅ Kontainer 'mcphost-web' aktif: ${GREEN}${CONTAINER_STATUS}${NC}\n"

# 2. Proses argumen baris perintah
PROMPT=""
REGION="denpasar"
DISABLE_BUILTIN=""

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --prompt) PROMPT="$2"; shift ;;
        --region) REGION="$2"; shift ;;
        --disable-builtin) DISABLE_BUILTIN="--disable-builtin" ;;
        *) echo -e "${RED}Opsi tidak dikenal: $1${NC}"; exit 1 ;;
    esac
    shift
done

# 3. Jalankan Artisan Command di dalam container
echo -e "⚡ ${BOLD}Menjalankan uji benchmark agen AI...${NC}"

CMD="php artisan bps:test-agent --region=${REGION} ${DISABLE_BUILTIN}"
if [ ! -z "$PROMPT" ]; then
    CMD="${CMD} --prompt=\"${PROMPT}\""
fi

# Eksekusi command
docker exec -it mcphost-web bash -c "${CMD}"

# Cek status kelulusan pengujian
EXIT_CODE=$?
echo -e "\n================================================================================"
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "🎉 ${GREEN}${BOLD}PENGUJIAN BENCHMARK AGEN SELESAI DENGAN SUKSES!${NC}"
    echo -e "Silakan periksa log komprehensif pada berkas: ${CYAN}storage/logs/bps_agent_tests.json${NC}"
else
    echo -e "❌ ${RED}${BOLD}PENGUJIAN BENCHMARK AGEN GAGAL!${NC}"
fi
echo -e "================================================================================"
