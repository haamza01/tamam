# Tamam development setup (Windows)

$ErrorActionPreference = "Stop"

Write-Host "Setting up Tamam development environment..." -ForegroundColor Cyan

if (-not (Get-Command pnpm -ErrorAction SilentlyContinue)) {
    Write-Error "pnpm is required. Install it with: npm install -g pnpm"
}

Write-Host "Installing Node workspace dependencies..."
pnpm install

if (Test-Path "frontend/.env.example") {
    if (-not (Test-Path "frontend/.env.local")) {
        Copy-Item "frontend/.env.example" "frontend/.env.local"
        Write-Host "Created frontend/.env.local"
    }
}

if (Test-Path "admin/.env.example") {
    if (-not (Test-Path "admin/.env.local")) {
        Copy-Item "admin/.env.example" "admin/.env.local"
        Write-Host "Created admin/.env.local"
    }
}

if (Test-Path "backend/.env.example") {
    if (-not (Test-Path "backend/.env")) {
        Copy-Item "backend/.env.example" "backend/.env"
        Write-Host "Created backend/.env"
    }
}

if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Host "Installing backend Composer dependencies..."
    Push-Location backend
    composer install
    if (-not (Select-String -Path ".env" -Pattern "^APP_KEY=base64:" -Quiet)) {
        php artisan key:generate
    }
    Pop-Location
} else {
    Write-Host "Composer not found. Skipping backend dependency install." -ForegroundColor Yellow
    Write-Host "Use Docker or install PHP 8.4 + Composer, then run: cd backend && composer install"
}

Write-Host "Setup complete." -ForegroundColor Green
Write-Host "Next steps:"
Write-Host "  pnpm dev:frontend   # http://localhost:3000"
Write-Host "  pnpm dev:admin      # http://localhost:3001"
Write-Host "  docker compose up   # full stack (requires Docker)"
