# Phase 1E — Listings core (no images)

**Status:** Complete  
**Branch:** `phase/1e-listings-core`  
**Depends on:** Phase 1D (`v0.4.0-phase1d`)

## Scope delivered

### Database
- `category_attributes`, `category_attribute_translations`, `category_attribute_options`, `category_attribute_option_translations`
- `listings` (UUID PK, soft deletes, optimistic `version`)
- `listing_attribute_values` (typed relational columns)
- `listing_statistics` (view/favourite/message counters — write-on-create, read-only in Phase 1E)
- PostgreSQL `CHECK (price IS NULL OR price >= 0)` on listings
- Indexes: owner+status, category+status, city+status, status+published_at, status+expires_at, published_at

### Category attributes
- Attributes defined on **leaf categories only** (no inheritance in Phase 1E)
- Supported types: text, long_text, number, price, dropdown, radio, checkbox, boolean, date, multi_select
- Values stored in typed columns per `DATABASE.md` (`value_text`, `value_number`, `value_boolean`, `value_date`, `value_json`)
- `GET /api/v1/categories/{slug}/attributes` returns definitions for listing forms (public)
- Seeded attributes for `sedans`, `apartments`, `phones`

### Listings core
- `ListingService` — create, update, submit, public/owner queries
- `ListingStateMachine` — explicit transitions, audit, `published_at` / `expires_at`, category cache invalidation
- `ListingAttributeValidator` — required/type/option/category validation, duplicate slug rejection
- `CategoryListingCountService` — **activated**; counts **`published`** listings on leaf categories only
- `listings:recalculate-category-counts` artisan command for idempotent rebuild
- `SlugGenerator` — globally unique listing slugs with collision suffix
- `EnsurePhoneVerified` middleware on listing write routes
- `ListingPolicy` — owner/moderator/public visibility rules
- Prohibited-words check on title + description via `config/prohibited_words.php`

### API endpoints (implemented)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/api/v1/listings` | No | Published only; filters: `category_id`, `city_id`, `sort` (`latest`, `price_asc`, `price_desc`) |
| GET | `/api/v1/listings/{id}` | Optional | Published public; owner/moderator see workflow fields |
| GET | `/api/v1/listings/featured` | No | Published + `featured=true` |
| GET | `/api/v1/listings/latest` | No | Recent published |
| GET | `/api/v1/listings/{id}/similar` | No | Same category + city, published |
| POST | `/api/v1/listings` | User + phone verified | Creates **draft** |
| PUT | `/api/v1/listings/{id}` | Owner | PATCH semantics via partial validation |
| DELETE | `/api/v1/listings/{id}` | Owner | Soft delete → `deleted` status |
| POST | `/api/v1/listings/{id}/submit` | Owner | Draft/rejected → pending_review (or published for trusted auto-publish) |
| POST | `/api/v1/listings/{id}/pause` | Owner | Published → paused |
| POST | `/api/v1/listings/{id}/activate` | Owner | Paused → published |
| POST | `/api/v1/listings/{id}/mark-sold` | Owner | Published → sold |
| POST | `/api/v1/listings/{id}/renew` | Owner | Expired → published |
| POST | `/api/v1/listings/{id}/archive` | Owner | → archived |
| POST | `/api/v1/listings/{id}/restore` | Owner | Archived → draft |
| GET | `/api/v1/users/me/listings` | User | All owner statuses except deleted |
| GET | `/api/v1/users/me/listings/{id}/statistics` | Owner | Basic counters |
| GET | `/api/v1/categories/{slug}/attributes` | No | Attribute definitions |

### Lifecycle states

`draft`, `pending_review`, `published`, `rejected`, `paused`, `sold`, `expired`, `archived`, `blocked`, `deleted`

### Transition matrix (owner unless noted)

| From | Allowed targets |
|------|-----------------|
| draft | pending_review, published (trusted auto-publish), deleted |
| pending_review | published (moderator `approve`), rejected (moderator), blocked (moderator), deleted |
| rejected | pending_review, deleted |
| published | paused, sold, expired (scheduler — deferred job), archived, pending_review (significant edit remoderation), blocked (moderator), deleted |
| paused | published, archived, deleted |
| sold | archived, deleted |
| expired | published (renew), archived, deleted |
| archived | published, deleted |
| blocked | deleted |
| deleted | — |

Invalid transitions return **409** with `listing.invalid_transition`.

Moderator `approve` / `reject` / `block` exist on `ListingStateMachine` for tests and Phase 1J; **no admin HTTP endpoints** in Phase 1E.

### Price storage

- Column: `decimal(12,2) NULL`
- Database constraint: non-negative when present
- Null when `price_type` is `free` or `contact_for_price`
- Currency: `char(3)`, default `QAR`

### Public vs owner visibility

| Field | Public (published) | Owner (any accessible status) |
|-------|-------------------|-------------------------------|
| Core content (title, description, price, location, attributes) | ✓ | ✓ |
| `images` | `[]` (Phase 1F) | `[]` |
| `status`, `rejection_reason`, `version` | Hidden | ✓ |
| `moderation_notes` | Never exposed | Never exposed |
| Seller | `PublicSellerResource` (no email/phone) | Same on public detail route |

Draft/pending/rejected listings return **404** to unauthenticated/non-owner users.

### listing_count

**Activated in Phase 1E.**

- Increments when a listing enters `published`
- Decrements when leaving `published` (pause, sold, reject, delete, archive, block, remoderation, category move while published)
- Row-locked category updates inside transactions
- `php artisan listings:recalculate-category-counts` rebuilds from live data

### Validation & error codes

| Code | When |
|------|------|
| `listing.prohibited_content` | Prohibited word in title/description |
| `listing.attribute_required` | Missing required attribute on submit |
| `listing.attribute_invalid` | Wrong type/range |
| `listing.attribute_not_allowed` | Attribute not on selected category |
| `listing.attribute_duplicate` | Duplicate attribute slug in payload |
| `listing.invalid_transition` | Disallowed lifecycle action |
| `listing.not_editable` | Update/transition in wrong state or non-owner |
| `listing.not_found` | Hidden or missing listing |
| `listing.limit_reached` | Max active listings exceeded |
| `category.must_be_leaf` | Non-leaf category selected |
| `listing.invalid_location` | Inactive or inconsistent city/district |

Protected fields (`status`, `user_id`, `published_at`, `moderation_notes`, etc.) are **rejected** by Form Requests.

### Audit events

`listing.created`, `listing.updated`, `listing.submitted`, `listing.published`, `listing.paused`, `listing.rejected`, `listing.sold`, `listing.archived`, `listing.blocked`, `listing.deleted`, `listing.restored` — metadata sanitised (no full descriptions, tokens, or private contact data).

### Security

- `auth:api` + `account.active` on authenticated routes
- `phone.verified` on listing writes
- `listing-write` rate limiter on create/update/delete/transitions
- IDOR prevention via `ListingPolicy` + service ownership checks
- No listing response caching in Phase 1E

## Deferred / out of scope

- Listing images (Phase 1F) — API returns `images: []`
- Min-image requirement on submit (Phase 1F)
- Admin moderation HTTP endpoints (Phase 1J)
- Search / FTS (Phase 1G)
- Expiration scheduler job (foundation only; `expired` state exists)
- Favourites, messaging, reports, payments, promotions UI
- Public seller profile pages
- Frontend listing UI

## Resolved ambiguities (documentation)

| Topic | Decision |
|-------|----------|
| `listing_type` / `country_id` on listings | Omitted; country derived via `city_id` (technical design §7.2) |
| Min images on submit | Deferred to Phase 1F |
| Moderation endpoints | State machine only; HTTP in Phase 1J |
| `listing_count` | Activated counting `published` only |

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
pnpm typecheck
pnpm lint
pnpm build
```
