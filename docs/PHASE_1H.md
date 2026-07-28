# Phase 1H — Favorites

**Status:** Completed  
**Branch:** `phase/1h-favorites`  
**Depends on:** Phase 1E (listing lifecycle), Phase 1F (listing images), Phase 1G (public listing visibility)

## Overview

Phase 1H implements authenticated favorites: add, remove, and list saved listings. It maintains `listing_statistics.favorites_count` atomically and reuses the existing public listing visibility rules. Backend-only — no frontend favourite UI.

## Documentation review

| Topic | Source | Phase 1H decision |
|-------|--------|-------------------|
| Duplicate POST | Technical design §21 | **409 Conflict** — `favorite.already_exists` |
| Missing DELETE | Listing delete idempotency pattern | **200 OK** idempotent success |
| POST success | REST conventions + API contract | **201 Created** |
| Non-favoritable listing | Technical design §9.1, listing API | **404** — `listing.not_found` (no leakage) |
| Own listing | Technical design §9.1 | **403** — `favorite.own_listing` |
| `is_favorited` on cards | Technical design §20 (`is_favorited?`) | **Deferred** to frontend phase |
| Statistics semantics | Technical design §9.1 | Count **persisted favourite rows** |
| List ordering | Standard UX expectation | `favorites.created_at DESC`, `id DESC` |
| Expiry | `ListingExpiryService` | Called once per request; expired listings not favoritable and excluded from list |
| Audit logging | Technical design §9 | **Not added** (not required) |
| User FK delete | DATABASE.md, `refresh_tokens` pattern | `cascadeOnDelete` |
| Listing FK delete | DATABASE.md, `listing_statistics` pattern | `cascadeOnDelete` |
| Auth middleware | Technical design §9.1 | `auth:api`, `account.active` (no `phone.verified`) |
| Notifications | Out of scope | Deferred to Phase 1K |

No material documentation conflicts were found.

## Database schema

Migration: `2026_07_28_600001_create_favorites_table.php`

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID PK | |
| `user_id` | UUID FK → `users` | `cascadeOnDelete` |
| `listing_id` | UUID FK → `listings` | `cascadeOnDelete` |
| `created_at`, `updated_at` | timestamps | |

**Constraints**

- Unique `(user_id, listing_id)`
- Index on `listing_id` (for statistics / listing-scoped queries)

**Not included:** notes, folders, types, ranking, soft deletes, price snapshots.

## Favourite eligibility

A listing may be favourited only when **all** of the following hold:

1. User is authenticated with an active account
2. Listing exists and is not soft-deleted
3. Listing passes `PublicListingQueryBuilder::applyPublicVisibility()`:
   - Status `published`
   - Not past `expires_at` (expiry reconciled once via `ListingExpiryService::expireDue()`)
   - Active, non-deleted category
   - Active city
   - Active district when `district_id` is set
4. User is **not** the listing owner
5. No existing favourite row for `(user_id, listing_id)`

Non-eligible listings return **404** `listing.not_found` — same anti-enumeration pattern as public listing detail.

## Endpoints

| Method | Path | Auth | Success | Response body |
|--------|------|------|---------|---------------|
| POST | `/api/v1/listings/{id}/favorite` | Bearer JWT | **201** | `{ favorite: { listing_id, created_at } }` |
| DELETE | `/api/v1/listings/{id}/favorite` | Bearer JWT | **200** | Message only |
| GET | `/api/v1/users/me/favorites` | Bearer JWT | **200** | Paginated `ListingCardResource` items |

**Pagination:** `page` (default 1), `per_page` (default 20, max 100).

## Duplicate and idempotency semantics

| Scenario | Behaviour |
|----------|-----------|
| Duplicate POST (same user + listing) | **409** `favorite.already_exists`; count unchanged |
| Concurrent duplicate POST | Unique constraint → **409**; count incremented once |
| DELETE when favourite exists | **200**; row deleted; count decremented once |
| DELETE when favourite missing | **200** idempotent; count unchanged |
| Concurrent duplicate DELETE | **200**; count decremented at most once (`GREATEST(0, …)`) |

## Statistics update strategy

`ListingStatisticsCounter` uses atomic SQL:

- Increment: `favorites_count = favorites_count + 1`
- Decrement: `favorites_count = GREATEST(0, favorites_count - 1)`
- Creates statistics row if missing on first increment

Favourite mutation and count update run in a single database transaction.

**Semantics:** `favorites_count` reflects persisted favourite rows, including favourites on listings that later become unavailable. Unavailable listings are hidden from the user's favourites list but rows are not auto-deleted.

## Expiry and soft-delete behaviour

| Event | Add favourite | Favourites list | Favourite row | `favorites_count` |
|-------|---------------|-----------------|---------------|-------------------|
| Listing expires | Rejected (404) | Excluded | Retained | Retained |
| Listing soft-deleted | Rejected (404) | Excluded | Retained until listing hard-deleted | Retained until cascade |
| Listing hard-deleted | N/A | Excluded | Cascade deleted | Cascade deleted with statistics |
| User deleted | N/A | N/A | Cascade deleted | Decremented via app logic before user delete, or cascade removes rows |

## Concurrency strategy

- Unique `(user_id, listing_id)` prevents duplicate rows
- DB transactions wrap favourite insert/delete + statistics update
- `QueryException` unique violations mapped to **409** (no raw SQLSTATE leakage)
- Application-level existence checks are not relied upon alone

## Error codes

| HTTP | Code | When |
|------|------|------|
| 401 | `auth.unauthenticated` | Missing/invalid token |
| 403 | `favorite.own_listing` | Owner attempts to favourite own listing |
| 404 | `listing.not_found` | Listing missing, soft-deleted, or not publicly favouritable |
| 409 | `favorite.already_exists` | Duplicate POST |
| 429 | `auth.rate_limited` | Rate limit exceeded |

## Rate limiting

| Limiter | Limit | Scope |
|---------|-------|-------|
| `favorite` | 60 requests/minute | Per authenticated user (fallback: IP) |

Applies to POST, DELETE, and GET favourites endpoints.

## API resource shape

Favourites list reuses `ListingCardResource` with eager-loaded `category.translations`, `city.translations`, and `images`. Only **ready** images are exposed as `cover_image`. No `is_favorited` field in Phase 1H.

## Audit behaviour

Not implemented — favourites are not listed as auditable events in the approved Phase 1H scope.

## Files changed

**New**

- `database/migrations/2026_07_28_600001_create_favorites_table.php`
- `app/Models/Favorite.php`
- `app/Domain/Favorite/Exceptions/FavoriteException.php`
- `app/Application/Favorite/FavoriteService.php`
- `app/Application/Favorite/ListingStatisticsCounter.php`
- `app/Http/Controllers/Api/V1/FavoriteController.php`
- `tests/Feature/Favorite/*`

**Modified**

- `app/Application/Search/PublicListingQueryBuilder.php` — extracted `applyPublicVisibility()`
- `routes/api.php` — favourite routes
- `app/Exceptions/ApiExceptionHandler.php`
- `app/Providers/AppServiceProvider.php`
- `app/Models/Listing.php` — `favorites()` relation
- `tests/TestCase.php` — clear `favorite` rate limiter

## Tests

`tests/Feature/Favorite/` — migration schema, auth rejection, eligibility rules, duplicate/idempotent behaviour, statistics consistency, list ordering/pagination, visibility exclusions, N+1 guard, concurrency via unique constraint.

PHPUnit requires PostgreSQL.

## Deferred

- Frontend favourite button and favourites page
- `is_favorited` on listing cards (optional field in design)
- Notifications on favourite events (Phase 1K)
- Favourite folders, notes, saved searches
- Public “who favourited this listing” endpoint
- Phase 1I and later work

## Known limitations

- Orphan favourite rows remain when listings become unavailable (by design — rows cleaned on listing/user hard delete)
- `favorites_count` may exceed visible favourites count for listings that later expire or are paused
- No reconciliation artisan command (not documented)
