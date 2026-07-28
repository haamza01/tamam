# Phase 1G — Search & filtering

**Status:** Completed (hardened review)  
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
| Query constructor | Security requirements | **`plainto_tsquery` only** — user input never passed to `to_tsquery` |
| Popular searches | ADR-005, technical design §8.3 | Seeded static terms in `config/popular_searches.php` + cache |
| Search history | DATABASE.md (deferred) | **Not implemented** |
| Suggestions | Technical design §8.3 | Published listing title prefix + active category names |
| Category filter | Technical design §8.2 | Includes descendant categories |
| Parameter names | API_SPEC.md §Search | Primary names with **documented aliases only** |

## PostgreSQL FTS SQL (final)

### Generated search vector

```sql
GENERATED ALWAYS AS (
    setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
    setweight(to_tsvector('simple', coalesce(description, '')), 'B')
) STORED
```

- `title` and `description` are NOT NULL columns; `coalesce` guards expression safety for generated column stability.
- Empty description yields title-only vector.

### Match (keyword present)

```sql
search_vector @@ plainto_tsquery(?, ?)
-- bindings: ['simple', <normalized keyword>]
```

### Rank (sort = relevance)

```sql
ts_rank_cd(search_vector, plainto_tsquery(?, ?), 32) DESC
-- bindings: ['simple', <normalized keyword>]
```

**No raw user input is passed to `to_tsquery`.** Constants live in `App\Application\Search\SearchSql`.

### Keyword parsing

- Trim, collapse whitespace, strip control characters
- Min length 2, max 200, max 10 tokens
- Matchability checked via `plainto_tsquery(?, ?) <> ''::tsquery`
- Punctuation (`: & | ! ( ) " ' \`, Arabic + punctuation) safe — no SQL/tsquery syntax errors

## Sort options (documented only)

| Sort | Authorisation | Secondary ordering |
|------|---------------|-------------------|
| `relevance` | ADR-005; technical design §8.2; API_SPEC §Search | `published_at DESC`, `created_at DESC`, `id DESC` |
| `newest` | ADR-005; technical design §8.2; API_SPEC §Search | `published_at DESC`, `id DESC` |
| `oldest` | ADR-005; technical design §8.2; API_SPEC §Search | `published_at ASC`, `id ASC` |
| `price_asc` | Technical design §8.2; API_SPEC §Search | null prices last, `price ASC`, `id DESC` |
| `price_desc` | Technical design §8.2; API_SPEC §Search | null prices last, `price DESC`, `id DESC` |
| `most_viewed` | ADR-005 ("most viewed"); technical design §8.2; API_SPEC §Search | `views_count DESC`, `published_at DESC`, `id DESC` |

Removed: undocumented `latest` alias (browse listings only).

## Request parameters (API_SPEC)

| Parameter | Documented alias | Notes |
|-----------|------------------|-------|
| `keyword` | — | Optional |
| `category` | `category_id` | UUID; includes descendants |
| `city` | `city_id` | Active city |
| `district` | `district_id` | Requires `city` |
| `price_min`, `price_max`, `price_type`, `condition` | — | |
| `sort`, `page`, `limit` | `per_page` | Default 20, max 100 |
| `attr[{slug}]` | — | **No** `attributes[...]` alias |

## Database indexes

| Index | Purpose |
|-------|---------|
| `listings_search_vector_idx` | GIN on `search_vector` |
| `listings_published_title_prefix_idx` | Partial btree `lower(title) varchar_pattern_ops` WHERE published |
| `category_translations_name_prefix_idx` | Btree `lower(name) varchar_pattern_ops` for category suggestions |

Migration: `2026_07_28_400007_add_search_suggestion_indexes.php`

## Suggestions

### SQL shape

**Listing titles** (via shared public visibility query builder):

```sql
lower(title) LIKE lower(?) || '%'
-- binding: escaped prefix (%, _, \ neutralised)
```

**Category names**:

```sql
lower(category_translations.name) LIKE lower(?) || '%'
-- active categories only
```

### Caching

**Listing-title and category suggestions are not cached.** Each request re-queries live public visibility rules. This prevents stale private/unpublished title leakage.

Popular searches remain cached (`search:popular`, TTL 3600s) — seeded static terms only.

### Response shape

```json
{ "type": "listing_title|category", "value": "<exact title or category name>", "label": null }
```

- Deduplicated case-insensitively
- Max 10 results
- Locale-aware category names (`Accept-Language` with ar/en fallback)

## Attribute filters

Handled by `SearchAttributeFilterApplier` — EXISTS subqueries, AND semantics.

| Type | Input | Comparison |
|------|-------|------------|
| `text`, `long_text` | string | exact `value_text` |
| `number`, `price` | numeric | exact `value_number`; min/max bounds enforced |
| `boolean` | true/false/1/0/yes/no | `value_boolean` |
| `date` | `YYYY-MM-DD` | `value_date` |
| `dropdown`, `radio` | string | exact `value_text`; must be approved option |
| `multi_select`, `checkbox` | array | JSON contains each value (AND) |

Rules:

- Max **20** filters (`search.max_attribute_filters`)
- Duplicate slugs rejected (`search.duplicate_attribute_filter`)
- Only `filterable = true` attributes
- Attribute must belong to listing's category (`ca.category_id = listings.category_id`)
- When `category` filter set, attribute must exist on category or descendant
- Null-price listings excluded from price min/max filters

## Public visibility

Reuses `scopePubliclyVisible()` plus:

- Active, non-deleted category
- Active city
- District null OR active district
- `ListingExpiryService::expireDue()` once per request

## Endpoints

| Method | Path | Rate limit |
|--------|------|------------|
| GET | `/api/v1/search` | 60/min/IP |
| GET | `/api/v1/search/suggestions` | 120/min/IP |
| GET | `/api/v1/search/popular` | 60/min/IP |

## EXPLAIN findings (seeded database)

Representative plans observed:

1. **Keyword FTS** — uses index scan on partial title index with `Filter: (search_vector @@ ...)` at low row counts; GIN index `listings_search_vector_idx` present for scale.
2. **Title prefix suggestions** — `Index Scan using listings_published_title_prefix_idx` with range condition on `lower(title)`.
3. **Category prefix** — `Index Scan using category_translations_name_prefix_idx`.

## Tests

`tests/Feature/Search/` — includes hardening tests for tsquery safety, vector null/updates, attribute AND semantics, suggestion visibility, inactive districts, parameter contract.

PHPUnit requires PostgreSQL.

## Deferred

- Meilisearch / Elasticsearch / pg_trgm
- Saved searches / search history / suggestion caching
- Frontend search UI

## Known limitations

- `simple` FTS: no Arabic stemming
- At very low listing volume PostgreSQL may choose partial btree + filter over GIN bitmap scan
- PRODUCT_REQUIREMENTS spelling variations deferred
