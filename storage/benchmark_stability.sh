#!/usr/bin/env bash
# ==============================================================================
# 📊 BPS AI Agent Multi-Tool & Multi-Step Stability Benchmark
# ==============================================================================
set -e

# ANSI Color Codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${BLUE}==============================================================${NC}"
echo -e "${BLUE}🚀 PRE-FLIGHT CHECK: VERIFYING ECOSYSTEM STATUS...${NC}"
echo -e "${BLUE}==============================================================${NC}"

# Check if docker-compose services are healthy
echo -e "${CYAN}🐳 Checking Docker container statuses...${NC}"
CONTAINER_STATUS=$(docker compose ps --format json)

# Ensure the worker is started and running
if [[ $CONTAINER_STATUS != *"mcphost-worker"* || $CONTAINER_STATUS == *"exited"* ]]; then
    echo -e "${YELLOW}⚠️ Worker container is stopped or exited. Starting it now...${NC}"
    docker compose start worker
    sleep 2
fi

# Confirm all core services are running
RUNNING_SERVICES=$(docker compose ps --filter "status=running" --services)
echo -e "${GREEN}✅ Active services:${NC}"
echo -e "${NC}$RUNNING_SERVICES"

# Clear Laravel config & optimization cache before run
# PENTING: Untuk memastikan perubahan kode (seperti MaxSteps) terdeteksi secara instan!
echo -e "\n${CYAN}⚙️ Clearing application caches and flushing Redis to ensure clean state...${NC}"
docker compose exec web php artisan config:clear
docker compose exec web php artisan route:clear
docker compose exec web php artisan optimize:clear
docker compose exec redis redis-cli flushall

# Restart worker if it was using queue:work to reload memory
echo -e "${CYAN}🔄 Restarting queue workers...${NC}"
docker compose restart worker
sleep 2

echo -e "\n${BLUE}==============================================================${NC}"
echo -e "${BLUE}🏃 RUNNING STABILITY BENCHMARK (3 SCENARIOS)...${NC}"
echo -e "${BLUE}==============================================================${NC}\n"

# Execute the benchmark
set +e
docker compose exec web php storage/benchmark.php
BENCHMARK_EXIT_CODE=$?
set -e

echo -e "\n${BLUE}==============================================================${NC}"
if [ $BENCHMARK_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}🎉 BENCHMARK RUN COMPLETELY STABLE! ALL TESTS PASSED!${NC}"
else
    echo -e "${RED}⚠️ BENCHMARK FAILED! STABILITY CONSTRAINTS VIOLATED!${NC}"
fi
echo -e "${BLUE}==============================================================${NC}"

exit $BENCHMARK_EXIT_CODE
