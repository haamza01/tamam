#!/usr/bin/env bash
set -euo pipefail

echo "Setting up Tamam development environment..."

if ! command -v pnpm >/dev/null 2>&1; then
  echo "pnpm is required. Install it with: npm install -g pnpm" >&2
  exit 1
fi

pnpm install

if [ -f frontend/.env.example ] && [ ! -f frontend/.env.local ]; then
  cp frontend/.env.example frontend/.env.local
  echo "Created frontend/.env.local"
fi

if [ -f admin/.env.example ] && [ ! -f admin/.env.local ]; then
  cp admin/.env.example admin/.env.local
  echo "Created admin/.env.local"
fi

if [ -f backend/.env.example ] && [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
  echo "Created backend/.env"
fi

if command -v composer >/dev/null 2>&1; then
  echo "Installing backend Composer dependencies..."
  (
    cd backend
    composer install
    if ! grep -q '^APP_KEY=base64:' .env; then
      php artisan key:generate
    fi
  )
else
  echo "Composer not found. Skipping backend dependency install."
  echo "Use Docker or install PHP 8.4 + Composer, then run: cd backend && composer install"
fi

echo "Setup complete."
echo "Next steps:"
echo "  pnpm dev:frontend   # http://localhost:3000"
echo "  pnpm dev:admin      # http://localhost:3001"
echo "  docker compose up   # full stack (requires Docker)"
