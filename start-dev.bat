@echo off
rem Mcphost Development Server Starter (Windows CMD)
rem Runs the entire development environment in an isolated Docker environment.

echo ==================================================
echo      MCPHost Gateway - Dockerized Local Dev       
echo ==================================================
echo.
echo Memulai seluruh service development di dalam Docker...
echo.

docker compose up --build
