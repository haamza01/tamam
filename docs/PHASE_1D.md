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
- `CategoryResource` — UUID `id`, `slug`, localized `name`, `icon`, `image`, `sort_order`, `listing_count` (reserved, always `0` in Phase 1D), optional `children`
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

Supported locales are defined in `config/locales.php` (mirrors `shared/src/constants/locales.ts`).

---

## Architecture notes

### `listing_count` (reserved)

The `categories.listing_count` column and API field are **schema placeholders for Phase 1E+**. In Phase 1D the value is always `0` (seeder default); no listing lifecycle exists yet.

**Planned consistency (Phase 1E+, per technical design):**

| Event | Counter behaviour |
|-------|-------------------|
| Listing published | Increment leaf category count |
| Listing unpublished / paused / rejected / expired | Decrement if previously counted |
| Listing soft-deleted | Decrement if was published |
| Listing restored | Re-evaluate status; increment if published |
| Category changed on listing | Decrement old leaf; increment new leaf |
| Moderation approve | Increment on transition to published |
| Moderation reject (from pending) | No change (never published) |

Implementation will use `UpdateCategoryListingCountJob` (async) with idempotent recalculation available for repair. Parent category rollup (if needed for browse) is deferred to search/filter phase.

The field is exposed in `CategoryResource` now so clients can rely on the response shape without a breaking change in Phase 1E.

### Cache invalidation

Catalog cache keys are locale-suffixed (`:{locale}`). Invalidation runs via Eloquent observers on **any** write that affects public catalog output:

| Model | Observer | Triggers |
|-------|----------|----------|
| `Category` | `CategoryObserver` | `saved`, `deleted`, `restored` — covers `status`, `sort_order`, `parent_id`, slug, icon, image, hierarchy |
| `CategoryTranslation` | `CategoryTranslationObserver` | `saved`, `deleted` — translation name/description changes |
| `Country`, `City`, `District` | `LocationObserver` | `saved`, `deleted` — covers `is_active`, `sort_order`, slug, parent FK |
| `CountryTranslation`, `CityTranslation`, `DistrictTranslation` | `LocationTranslationObserver` | `saved`, `deleted` |

Seeders call `CatalogCacheService::flushCategories()` / implicit observer flush after bulk inserts.

### Slugs

| Entity | Source | Scope | Immutability |
|--------|--------|-------|--------------|
| Category | Explicit at create (seed/admin); future admin may derive from English name via `SlugGenerator` | Globally unique | **Immutable** after create — URL stability for `/categories/{slug}`; translated name changes do not alter slug |
| Country | Explicit at seed (e.g. `qatar`) | Globally unique | Immutable after create |
| City | Explicit at seed (e.g. `doha`) | Unique per country | Immutable after create |
| District | Explicit at seed (e.g. `west-bay`) | Unique per city | Immutable after create |

Translated display names live in `*_translations` tables and can change independently of slugs.

### Seed scope (Qatar MVP)

Location seed data is **intentionally minimal** for Qatar launch:

- **1 country:** Qatar (`QA`)
- **4 cities:** Doha, Al Wakrah, Al Khor, Lusail
- **5 districts:** Doha only (West Bay, The Pearl, Al Sadd, Al Rayyan, Lusail Marina)

Other cities have no districts in MVP. Additional countries (GCC expansion), cities, and districts will be added via admin tooling or seed migrations in future phases without schema changes.

Category seed covers the full launch tree per technical design §19.2.
