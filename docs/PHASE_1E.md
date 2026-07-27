# Phase 1E — Listings core (no images)

**Status:** Complete (hardening review)  
**Branch:** `phase/1e-listings-core`  
**Depends on:** Phase 1D (`v0.4.0-phase1d`)

## Scope delivered

### Database
- `category_attributes`, `category_attribute_translations`, `category_attribute_options`, `category_attribute_option_translations`
- `listings` (UUID PK, soft deletes, optimistic `version`)
- `listing_attribute_values` (typed relational columns)
- `listing_statistics` (view/favourite/message counters — write-on-create, read-only in Phase 1E)
- PostgreSQL `CHECK (price IS NULL OR price >= 0)` on listings
- PostgreSQL `CHECK listings_deleted_consistency` — `(status='deleted' AND deleted_at NOT NULL) OR (status<>'deleted' AND deleted_at IS NULL)`
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
- `CategoryListingCountService` — counts **publicly visible** published listings (not soft-deleted, not past `expires_at`)
- `listings:recalculate-category-counts` — idempotent rebuild from live data
- `listings:expire` — idempotent transition of published listings past `expires_at` to `expired` (scheduler deferred)
- `HasListingVisibility` — shared public visibility / counting rules
- `SlugGenerator` — globally unique listing slugs with collision suffix
- `EnsurePhoneVerified` middleware on listing write routes
- `ListingPolicy` — owner/moderator/public visibility rules
- Prohibited-words check on title + description via `config/prohibited_words.php`

### API endpoints (implemented)

| Method | Path | Auth | Middleware | Policy / ownership | Purpose |
|--------|------|------|------------|-------------------|---------|
| GET | `/api/v1/listings` | No | — | Public visibility scope | Browse published, non-expired listings |
| GET | `/api/v1/listings/{id}` | Optional | — | `view` | Public detail or owner/moderator workflow view |
| GET | `/api/v1/listings/featured` | No | — | Public visibility scope | Featured published listings |
| GET | `/api/v1/listings/latest` | No | — | Public visibility scope | Recent published listings |
| GET | `/api/v1/listings/{id}/similar` | No | — | Public source listing must be visible | Same category + city |
| POST | `/api/v1/listings` | User | `auth:api`, `account.active`, `phone.verified`, `throttle:listing-write` | `create` | Create draft |
| PUT | `/api/v1/listings/{id}` | Owner | same + write throttle | `update` | Partial update; optional `version` for optimistic concurrency |
| DELETE | `/api/v1/listings/{id}` | Owner | same + write throttle | `delete` | Soft delete → `deleted` status (idempotent) |
| POST | `/api/v1/listings/{id}/submit` | Owner | same + write throttle | `transition` | Draft/rejected → pending_review (or published for trusted auto-publish) |
| POST | `/api/v1/listings/{id}/pause` | Owner | same + write throttle | `transition` | Published → paused |
| POST | `/api/v1/listings/{id}/activate` | Owner | same + write throttle | `transition` | Paused → published |
| POST | `/api/v1/listings/{id}/mark-sold` | Owner | same + write throttle | `transition` | Published → sold |
| POST | `/api/v1/listings/{id}/renew` | Owner | same + write throttle | `transition` | Expired → published |
| POST | `/api/v1/listings/{id}/archive` | Owner | same + write throttle | `transition` | → archived |
| POST | `/api/v1/listings/{id}/restore` | Owner | same + write throttle | `transition` | Archived → draft (idempotent) |
| GET | `/api/v1/users/me/listings` | User | `auth:api`, `account.active` | Owner scope | Owner listings excluding soft-deleted |
| GET | `/api/v1/users/me/listings/{id}/statistics` | Owner | `auth:api`, `account.active` | Owner + listing ownership | Basic counters |
| GET | `/api/v1/categories/{slug}/attributes` | No | — | — | Attribute definitions for forms |

**Not implemented in Phase 1E:** image endpoints, admin/moderator HTTP moderation routes, Phase 1F routes.

### Lifecycle states

`draft`, `pending_review`, `published`, `rejected`, `paused`, `sold`, `expired`, `archived`, `blocked`, `deleted`

### Delete / soft-delete invariant

**Single rule:** `status = deleted` **if and only if** `deleted_at IS NOT NULL`.

- Delete operation sets **both** `status = deleted` and `deleted_at = now()` in one transaction.
- Non-deleted rows must have `deleted_at IS NULL`.
- Soft-deleted listings have **no HTTP restore** in Phase 1E; `restore` applies only to **archived → draft**.
- Repeated delete is idempotent (200, no counter drift).

Enforced by DB constraint `listings_deleted_consistency` and `ListingStateMachine::softDelete()`.

### Expiration strategy (approved: option B)

1. **Query-time exclusion:** `scopePubliclyVisible()` excludes published listings with `expires_at <= now()` from public index, detail, featured, latest, and similar.
2. **Owner visibility:** owners (and moderators) may still view expired-but-not-yet-transitioned published listings with workflow fields.
3. **Background command:** `php artisan listings:expire` idempotently transitions `published` + past `expires_at` → `expired`, decrements `listing_count`, writes audit `listing.expired`.
4. **Scheduler:** deferred to a later phase; public queries never expose expired listings regardless.

### Transition matrix

| From | To | Actor | Idempotent | listing_count | Timestamps | Audit event |
|------|-----|-------|------------|---------------|------------|-------------|
| draft | pending_review | owner | submit if already pending | — | — | listing.submitted |
| draft | published | owner (trusted + setting) | — | +1 on publish | published_at, expires_at | listing.published |
| draft | deleted | owner | delete | — | deleted_at | listing.deleted |
| pending_review | published | moderator | approve | +1 | published_at, expires_at | listing.published |
| pending_review | rejected | moderator | reject | — | rejection_reason | listing.rejected |
| pending_review | blocked | moderator | — | — | — | listing.blocked |
| pending_review | deleted | owner | delete | — | deleted_at | listing.deleted |
| rejected | pending_review | owner | submit | — | clears rejection on publish path | listing.submitted |
| rejected | deleted | owner | delete | — | deleted_at | listing.deleted |
| published | paused | owner | pause | −1 | — | listing.paused |
| published | sold | owner | mark sold | −1 | sold_at | listing.sold |
| published | expired | system/command | expire | −1 | — | listing.expired |
| published | archived | owner | — | −1 | — | listing.archived |
| published | pending_review | owner edit (significant) | — | −1 | clears published_at/expires_at | listing.updated |
| published | blocked | moderator | — | −1 | — | listing.blocked |
| published | deleted | owner | delete | −1 | deleted_at | listing.deleted |
| paused | published | owner activate | activate | +1 | keeps published_at/expires_at on reactivate | listing.published |
| paused | archived | owner | — | — | — | listing.archived |
| paused | deleted | owner | delete | — | deleted_at | listing.deleted |
| sold | archived | owner | — | — | — | listing.archived |
| sold | deleted | owner | delete | — | deleted_at | listing.deleted |
| expired | published | owner renew | renew | +1 | new published_at/expires_at | listing.published |
| expired | archived | owner | — | — | — | listing.archived |
| expired | deleted | owner | delete | — | deleted_at | listing.deleted |
| archived | draft | owner restore | restore | — | — | listing.restored |
| archived | published | owner (direct transition if allowed) | — | +1 | published_at, expires_at | listing.published |
| archived | deleted | owner | delete | — | deleted_at | listing.deleted |
| blocked | deleted | owner | delete | — | deleted_at | listing.deleted |
| deleted | — | — | repeated delete OK | — | — | — |

Invalid transitions return **409** with `listing.invalid_transition`. Status cannot be set via create/PATCH (prohibited fields).

Moderator `approve` / `reject` / `block` exist on `ListingStateMachine` for tests and Phase 1J; **no admin HTTP endpoints** in Phase 1E.

### Optimistic concurrency

- Column: `listings.version` (integer, default 1, incremented on every successful owner update).
- Optional request field: `version` on `PUT /api/v1/listings/{id}`.
- When provided, must match the locked row; mismatch → **409** `listing.version_conflict`.
- When omitted, update proceeds (last-write-wins within the row lock).
- Lifecycle transitions do not accept client version (row lock only).

### Trusted seller auto-publish

Per `PHASE_1_TECHNICAL_DESIGN.md` §7.3: when `users.trusted_seller = true` **and** `platform_settings.auto_publish_for_trusted_users = true`, owner `submit` transitions draft/rejected → **published** (skips pending_review). Eligibility is server-side only (`trusted_seller` is a protected profile field). Default seeder value: setting **disabled**.

### Price storage

- Column: `decimal(12,2) NULL`
- Database constraint: non-negative when present
- Null when `price_type` is `free` or `contact_for_price`
- Currency: `char(3)`, default `QAR`

### Public vs owner visibility

| Field | Public (published, not expired) | Owner (any accessible status) |
|-------|--------------------------------|--------------------------------|
| Core content (title, description, price, location, attributes) | ✓ | ✓ |
| `images` | `[]` (Phase 1F) | `[]` |
| `status`, `rejection_reason`, `version` | Hidden | ✓ |
| `moderation_notes` | Never exposed | Never exposed |
| Seller | `PublicSellerResource` (no email/phone) | Same on public detail route |

Draft/pending/rejected/expired/archived/blocked listings return **404** to unauthenticated/non-owner users. Expired **published** listings (before command runs) also return **404** publicly but remain visible to owner.

### listing_count

**Activated in Phase 1E.**

- Counts only **publicly visible** published listings: `status=published`, `deleted_at IS NULL`, and (`expires_at IS NULL` OR `expires_at > now()`).
- Increments/decrements on lifecycle transitions and significant remoderation/category moves while counted.
- Row-locked category updates; counts floored at 0.
- `php artisan listings:recalculate-category-counts` rebuilds all categories (resets stale non-zero counts).

### Validation & error codes

| Code | When |
|------|------|
| `listing.prohibited_content` | Prohibited word in title/description |
| `listing.attribute_required` | Missing required attribute on submit |
| `listing.attribute_invalid` | Wrong type/range |
| `listing.attribute_not_allowed` | Attribute not on selected category |
| `listing.attribute_duplicate` | Duplicate attribute slug in payload |
| `listing.invalid_transition` | Disallowed lifecycle action |
| `listing.version_conflict` | Stale `version` on update |
| `listing.not_editable` | Update/transition in wrong state or non-owner |
| `listing.not_found` | Hidden or missing listing |
| `listing.limit_reached` | Max active listings exceeded |
| `category.must_be_leaf` | Non-leaf category selected |
| `listing.invalid_location` | Inactive or inconsistent city/district |

Protected fields (`status`, `user_id`, `published_at`, `moderation_notes`, etc.) are **rejected** by Form Requests.

### Audit events

`listing.created`, `listing.updated`, `listing.submitted`, `listing.published`, `listing.paused`, `listing.rejected`, `listing.sold`, `listing.archived`, `listing.blocked`, `listing.deleted`, `listing.restored`, `listing.expired` — metadata sanitised (no full descriptions, tokens, or private contact data).

### Security

- `auth:api` + `account.active` on authenticated routes
- `phone.verified` on listing writes
- `listing-write` rate limiter on create/update/delete/transitions
- IDOR prevention via `ListingPolicy` + service ownership checks; non-owners receive 403 on mutation, hidden listings 404 on read
- No listing response caching in Phase 1E

## Deferred / out of scope

- Listing images (Phase 1F) — API returns `images: []`
- Min-image requirement on submit (Phase 1F)
- Admin moderation HTTP endpoints (Phase 1J)
- Expiration **scheduler** job (command provided; cron deferred)
- Search / FTS (Phase 1G)
- Favourites, messaging, reports, payments, promotions UI
- Public seller profile pages
- Frontend listing UI
- HTTP restore of soft-deleted listings

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
pnpm typecheck
pnpm lint
pnpm build
```
