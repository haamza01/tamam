# Phase 1D — Locations & Categories

**Status:** Complete  
**Branch:** `phase/1d-locations-categories`  
**Depends on:** Phase 1C (`v0.3.0-phase1c`)

## Scope delivered

### Categories
- `categories` and `category_translations` tables (soft deletes on categories)
- `Category` model with parent/child hierarchy (max depth 3 via `category_max_depth` platform setting)
- `CategoryHierarchyValidator` — self-parent, circular reference, and depth checks (ready for future admin CRUD)
- Public read-only API:
  - `GET /api/v1/categories` — flat active list
  - `GET /api/v1/categories/tree` — nested tree
  - `GET /api/v1/categories/{slug}` — single category by slug
- `CategoryResource` — UUID `id`, `slug`, localized `name`, `icon`, `image`, `sort_order`, `listing_count`, optional `children`
- `CategorySeeder` — launch category tree (ar/en)

### Locations
- Hierarchy per technical design: **Country → City → District**
- Translation tables for ar/en names (`country_translations`, `city_translations`, `district_translations`)
- `is_active` and `sort_order` on all location entities
- Slugs: global on countries; scoped unique per parent for cities and districts
- Public read-only API:
  - `GET /api/v1/locations` — flat active countries, cities, districts
  - `GET /api/v1/locations/tree` — nested country → city → district tree
- Qatar launch seed (`CountrySeeder`, `CitySeeder`, `DistrictSeeder`)

### Cross-cutting
- Unified `ApiResponse` envelope on all endpoints
- Locale resolution via `?locale=ar|en` or `Accept-Language`
- Redis/file cache for category and location trees (TTL configurable via `CATALOG_CACHE_TTL`)
- Cache invalidation via model observers and seeder flush
- User `country_id` / `city_id` foreign keys added

## Out of scope (Phase 1E+)

- Category attributes and `/categories/{id}/attributes`
- Admin category/location CRUD
- Listings, search, messaging, favorites, notifications
- Public seller profiles

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
pnpm typecheck
pnpm lint
pnpm build
```

## Environment

| Variable | Default | Purpose |
|----------|---------|---------|
| `CATALOG_CACHE_TTL` | `3600` | Seconds to cache category/location trees |
