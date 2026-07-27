# Phase 1G — Search & filtering

**Status:** Completed  
**Branch:** `phase/1g-search-filtering`  
**Depends on:** Phase 1E (listing lifecycle), Phase 1F (listing images)

## Overview

Phase 1G implements PostgreSQL Full Text Search (FTS) for public listing discovery with filters, sorting, autocomplete suggestions, and seeded popular searches. No external search engine, no search event persistence, and no frontend search UI.

## Documentation review

| Topic | Source | Phase 1G decision |
|-------|--------|-------------------|
| Search engine | ADR-005 | PostgreSQL FTS only |
| FTS config | Technical design §28.4 | `simple` (no Arabic stemming) |
| Searchable fields | ADR-005, technical design §8 | `title` (weight A), `description` (weight B) |
| Vector maintenance | Technical design §8.2 | PostgreSQL **GENERATED ALWAYS … STORED** column |
| Query constructor | Security requirements | `plainto_tsquery('simple', ?)` via bound parameter |
| Popular searches | ADR-005, technical design §8.3 | Seeded static terms in `config/popular_searches.php` + cache |
| Search history | DATABASE.md (deferred) | **Not implemented** — no `search_history` table |
| Suggestions | Technical design §8.3 | Published listing title prefix + active category names |
| Category filter | Technical design §8.2 | Includes descendant categories |
| Parameter names | API_SPEC + technical design | API_SPEC names (`keyword`, `category`, `city`, `limit`) with aliases (`category_id`, `city_id`, `per_page`) |
| Sort options | ADR-005 + technical design | `relevance`, `newest`, `oldest`, `price_asc`, `price_desc`, `most_viewed` |

No material conflicts were found that block implementation.

## Database changes

Migration: `2026_07_28_400006_add_listings_search_vector.php`

```sql
ALTER TABLE listings ADD COLUMN search_vector tsvector
GENERATED ALWAYS AS (
    setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
    setweight(to_tsvector('simple', coalesce(description, '')), 'B')
) STORED;

CREATE INDEX listings_search_vector_idx ON listings USING GIN (search_vector);
```

- **Maintenance:** automatic on insert/update of `title` or `description`
- **Backfill:** existing rows populated on migration
- **No** search indexing of user/private fields

## Search configuration

File: `config/search.php`

| Setting | Default |
|---------|---------|
| FTS config | `simple` |
| Keyword min length | 2 |
| Keyword max length | 200 |
| Max tokens | 10 |
| Default per page | 20 |
| Max per page | 100 |
| Suggestion min prefix | 2 |
| Suggestion max results | 10 |
| Popular max results | 10 |

Popular terms: `config/popular_searches.php`

## Public visibility

Search reuses `Listing::scopePubliclyVisible()` plus:

- Active, non-deleted category (`whereHas('category')`)
- Active city (`whereHas('city')`)
- `ListingExpiryService::expireDue()` once per request (same as listing browse)

## Endpoints

| Method | Path | Rate limit | Auth |
|--------|------|------------|------|
| GET | `/api/v1/search` | 60/min/IP | Public |
| GET | `/api/v1/search/suggestions` | 120/min/IP | Public |
| GET | `/api/v1/search/popular` | 60/min/IP | Public |

### GET /api/v1/search

**Parameters (API_SPEC names, aliases accepted):**

| Parameter | Aliases | Notes |
|-----------|---------|-------|
| `keyword` | — | Optional; min 2 / max 200 chars; max 10 tokens |
| `category` | `category_id` | UUID; includes descendant categories |
| `city` | `city_id` | Active city UUID |
| `district` | `district_id` | Requires matching `city` |
| `price_min` | — | Numeric; excludes null-price listings |
| `price_max` | — | Numeric; excludes null-price listings |
| `price_type` | — | `fixed`, `negotiable`, `free`, `contact_for_price` |
| `condition` | — | `new`, `used`, `refurbished` |
| `sort` | — | See sorting below |
| `page` | — | Default 1 |
| `limit` | `per_page` | Default 20, max 100 |
| `attr[{slug}]` | `attributes[{slug}]` | Filterable category attributes only; AND semantics |

**Response:** Standard envelope with `ListingCardResource` items and pagination metadata.

### GET /api/v1/search/suggestions

| Parameter | Notes |
|-----------|-------|
| `q` or `keyword` | Required prefix; min 2 chars |

Returns `{ type: listing_title|category, value, label }` — max 10, deduplicated case-insensitively.

### GET /api/v1/search/popular

Returns seeded `{ term, rank }` from config (cached). No analytics persistence.

## Ranking

When `keyword` is present and sort is `relevance` (default):

```sql
ts_rank_cd(search_vector, to_tsquery('simple', ?), 32) DESC,
published_at DESC, created_at DESC, id DESC
```

Title matches rank above description-only matches via vector weights (A vs B).

## Sorting

| Sort | Behaviour |
|------|-----------|
| `relevance` | FTS rank; falls back to `newest` without keyword |
| `newest` (default without keyword) | `published_at DESC, id DESC` |
| `oldest` | `published_at ASC, id ASC` |
| `price_asc` | Null prices last, then `price ASC` |
| `price_desc` | Null prices last, then `price DESC` |
| `most_viewed` | `listing_statistics.views_count DESC` |
| `latest` | Alias for `newest` |

## Filters

- **Category:** descendant inclusion via `CategoryDescendantResolver` (cached)
- **Location:** district must belong to selected city
- **Price:** null-price listings excluded from min/max filters
- **Attributes:** `whereExists` subqueries per filter — no duplicate rows

## Caching

| Key | TTL | Content |
|-----|-----|---------|
| `search:popular` | 3600s | Popular terms |
| `search:category-descendants:{id}` | 3600s | Category tree IDs |
| `search:suggestions:{locale:prefix}` | 300s | Suggestion results |

Full search result pages are **not** cached.

## Privacy

- No search event recording
- No IP or user-agent storage for search
- No raw keyword logging in application audit tables
- Popular terms are admin-seeded only

## Architecture

```
SearchController
  → SearchService / SearchSuggestionService / PopularSearchService
  → PublicListingQueryBuilder (shared with ListingService::paginatePublic)
  → SearchQueryParser (plainto_tsquery)
  → ListingExpiryService
```

## Tests

`tests/Feature/Search/` — migration, visibility, parsing, ranking, filters, sort, pagination, suggestions, popular, rate limits.

PHPUnit uses PostgreSQL (`phpunit.xml`) because FTS requires `tsvector`.

## Deferred (not Phase 1G)

- Meilisearch / Elasticsearch
- Saved searches / search history
- Fuzzy matching / typo correction
- Geospatial search
- Frontend search UI (Phase 1 public frontend)
- Phase 1H favorites

## Known limitations

- `simple` FTS config: no Arabic stemming (documented in ADR-005)
- Suggestions use bounded `ILIKE prefix%` on titles (not FTS prefix)
- PRODUCT_REQUIREMENTS mentions spelling variations — deferred; not in approved Phase 1 scope
