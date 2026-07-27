# ADR-005: Search Engine Strategy

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Search is a primary marketplace feature. [SYSTEM_ARCHITECTURE.md](../SYSTEM_ARCHITECTURE.md) defines a phased approach: PostgreSQL FTS (Phase 1), Meilisearch (Phase 2), ElasticSearch (future).

Performance target: search responses **< 500 ms**.

Deferred post-MVP Core: saved searches, search history.

## Decision

### MVP Core: PostgreSQL Full Text Search

- Index listing `title` and `description` with language-aware FTS vectors
- Support Arabic and English queries
- Filters: category, city, district, price range, condition, price type, category attributes (filterable attributes from schema)
- Sorting: relevance, newest, oldest, price, most viewed
- Exclude blocked, deleted, inactive, and non-published listings
- Cache popular searches and category counts in Redis

### API endpoints (MVP Core)

- `GET /search`
- `GET /search/suggestions`
- `GET /search/popular`

### Post-MVP Core (deferred)

- `GET/POST/DELETE /users/me/searches` — saved searches with optional notifications
- `DELETE /users/me/search-history` — personal search history
- Tables: `saved_searches`, `search_history` (schema reserved in [DATABASE.md](../DATABASE.md))

### Phase 2: Meilisearch

- Migrate search reads to Meilisearch when listing volume or query complexity warrants it
- Keep PostgreSQL as source of truth; sync via queue jobs on listing publish/update/delete
- API contract unchanged; only backend search provider changes

## Consequences

### Positive

- No additional search infrastructure at launch
- Simpler operations and fewer moving parts
- Clear migration path documented

### Negative

- PostgreSQL FTS less feature-rich than Meilisearch for fuzzy/typo tolerance
- May require earlier Meilisearch migration if search quality feedback is poor

## Related documents

- [API_SPEC.md](../API_SPEC.md) — Search endpoints
- [PRODUCT_REQUIREMENTS.md](../PRODUCT_REQUIREMENTS.md) — Section 13
