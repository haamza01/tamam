# Tamam Backend

Laravel 12 API for the Tamam marketplace.

## Requirements

- PHP 8.4+
- Composer 2.x
- PostgreSQL 16
- Redis 7

## Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

## Health check

```http
GET /api/v1/health
```

## Clean Architecture placeholders

- `app/Domain/`
- `app/Application/`
- `app/Infrastructure/`

Authentication packages and implementation are deferred to Phase 1.

## Docker

Use the root `docker-compose.yml` once Docker is available.
