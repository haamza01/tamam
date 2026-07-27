#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-all}"

case "$TARGET" in
  frontend)
    pnpm dev:frontend
    ;;
  admin)
    pnpm dev:admin
    ;;
  all)
    echo "Starting frontend and admin dev servers..."
    echo "Frontend: http://localhost:3000"
    echo "Admin:    http://localhost:3001"
    pnpm dev:frontend &
    pnpm dev:admin &
    wait
    ;;
  *)
    echo "Usage: $0 [frontend|admin|all]" >&2
    exit 1
    ;;
esac
