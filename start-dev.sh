#!/bin/bash

# Mcphost Development Server Starter
# Runs the entire development environment in an isolated Docker environment.
# No local host PHP or PostgreSQL drivers required!

set -e

# Warna teks
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}     MCPHost Gateway - Dockerized Local Dev       ${NC}"
echo -e "${BLUE}==================================================${NC}"

# Jalankan docker-compose build & up
echo -e "${GREEN}Memulai seluruh service development di dalam Docker...${NC}"
echo -e "${BLUE}Daftar Port & Layanan yang Terbuka:${NC}"
echo -e "  - 🌐 Web Server Utama (Nginx & PHP-FPM) : ${GREEN}http://127.0.0.1:8900${NC}"
echo -e "  - ⚡ Vite Dev Server (Hot Module Reload)  : ${GREEN}http://127.0.0.1:5173${NC}"
echo -e "  - 📡 Laravel Reverb (WebSockets)          : ${GREEN}ws://127.0.0.1:8080${NC}"
echo -e "  - 🐘 PostgreSQL Database Server           : ${GREEN}localhost:54322${NC} (DB: mcphost, User: mcphost)"
echo -e "  - 🔴 Redis Cache & Queue Server          : ${GREEN}localhost:63792${NC}"
echo -e ""
docker compose up --build
