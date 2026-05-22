# Mcphost Development Server Starter (Windows PowerShell)
# Runs the entire development environment in an isolated Docker environment.

Write-Host "==================================================" -ForegroundColor Blue
Write-Host "     MCPHost Gateway - Dockerized Local Dev       " -ForegroundColor Blue
Write-Host "==================================================" -ForegroundColor Blue
Write-Host ""
Write-Host "Memulai seluruh service development di dalam Docker..." -ForegroundColor Green
Write-Host ""

docker compose up --build
