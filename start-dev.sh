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
docker compose up --build
