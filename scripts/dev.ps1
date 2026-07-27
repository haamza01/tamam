param(
    [ValidateSet("frontend", "admin", "all")]
    [string]$Target = "all"
)

$ErrorActionPreference = "Stop"

switch ($Target) {
    "frontend" {
        pnpm dev:frontend
    }
    "admin" {
        pnpm dev:admin
    }
    "all" {
        Write-Host "Starting frontend and admin dev servers..." -ForegroundColor Cyan
        Write-Host "Frontend: http://localhost:3000"
        Write-Host "Admin:    http://localhost:3001"
        $root = Split-Path $PSScriptRoot -Parent
        Start-Process pnpm -ArgumentList "dev:frontend" -WorkingDirectory $root
        Start-Process pnpm -ArgumentList "dev:admin" -WorkingDirectory $root
    }
}
