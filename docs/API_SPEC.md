# API_SPEC.md

# Tamam Marketplace API Specification

Version: 1.1

API Style: REST API

Response Format: JSON

Authentication: JWT (access token + refresh token)

Source of Truth: Business behavior is defined in [PRODUCT_REQUIREMENTS.md](./PRODUCT_REQUIREMENTS.md). This document describes the HTTP API contract aligned to those requirements.

Architecture decisions: See [docs/adr/](./adr/README.md).

---

# API Principles

All APIs must be:

- RESTful
- Secure
- Versioned
- Consistent
- Documented
- Scalable

Base URL

/api/v1

Future versions

/api/v2

---

# Response Format

All responses use a single envelope. Paginated list responses nest `data` and `meta` inside the envelope.

Success

```json
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Error

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {}
}
```

Paginated success

```json
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": [],
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 250,
        "last_page": 13
    }
}
```

---

# Authentication

Authentication uses **JWT access tokens** with **refresh tokens** and secure token rotation.

Rules:

- Access tokens are short-lived (default **15 minutes**, `JWT_TTL` / `JWT_ACCESS_TTL`) and returned in the JSON body only — never stored in `localStorage` by the backend; clients should keep them in memory.
- Refresh tokens are long-lived (default 14 days, `JWT_REFRESH_TTL_DAYS`) and stored server-side as SHA-256 hashes in `refresh_tokens`.
- The raw refresh token is sent only in the `tamam_refresh_token` httpOnly cookie (path `/api/v1/auth`, Secure in production).
- `POST /auth/refresh` requires the CSRF double-submit pattern: `tamam_auth_csrf` cookie plus matching `X-Auth-CSRF` header.
- Refresh rotates the refresh token on each successful use; reuse of a revoked token revokes all sessions for that user.
- All active refresh tokens are revoked after password reset or logout-all.
- Phone OTP is used for phone verification and password reset (not email).
- See [ADR-001](./adr/001-authentication-jwt-refresh.md) and [PHASE_1B.md](./PHASE_1B.md).

## Rate limits

| Endpoint group | Limit |
|----------------|-------|
| register, login | 5 requests/minute per IP |
| refresh | 20 requests/minute per IP |
| forgot/reset password | 3 requests/hour per IP + identifier |
| verify-phone | 10 requests/minute per user |
| resend-phone-code | 3 requests/hour per user |

Rate-limited responses use HTTP 429 with the unified error envelope.

POST

/auth/register

Register new individual user (`full_name`, `phone`, optional `email`, `password`, `password_confirmation`). Assigns `user` role only.

---

POST

/auth/login

User login with `identifier` (phone or email) and `password`. Returns access token JSON and sets refresh + CSRF cookies.

---

POST

/auth/logout

Logout current user (revokes current refresh token). Requires Bearer JWT.

---

POST

/auth/logout-all

Revoke all refresh tokens for the authenticated user. Requires Bearer JWT.

---

POST

/auth/refresh

Refresh access token (rotates refresh token). Requires refresh cookie + CSRF cookie/header. No Bearer token.

---

POST

/auth/forgot-password

Request password-reset OTP. Always returns the same success message whether or not the account exists.

---

POST

/auth/reset-password

Verify OTP and set a new password (revokes all active refresh tokens).

---

POST

/auth/verify-phone

Verify phone number with OTP. Sets `phone_verified_at`. Requires Bearer JWT.

---

POST

/auth/resend-phone-code

Resend phone verification OTP. Requires Bearer JWT. Subject to cooldown.

---

GET

/auth/me

Get current authenticated user summary (session check). Requires Bearer JWT.

---

**Deferred (not Phase 1B):**

POST

/auth/verify-email

Verify email address

---

# Users

Authenticated profile management uses `/profile` (Phase 1C). Legacy `/users/me/*` paths below remain documented for later phases.

## Profile (Phase 1C)

GET

/profile

Get the authenticated user's profile. Requires Bearer JWT and an active account.

---

PATCH

/profile

Partially update permitted profile fields (`full_name`, `email`, `preferred_language`, `username`, `bio`). Phone, status, roles, and other protected fields are rejected. Rate limit: 10 requests/minute per user.

---

POST

/profile/avatar

Upload or replace profile avatar (multipart `avatar` field). Max 5 MB; JPEG, PNG, or WebP only. Rate limit: 5 requests/minute per user.

---

DELETE

/profile/avatar

Remove profile avatar and revert to the default fallback URL.

---

Authenticated account management also uses `/users/me/*` (future phases).

GET

/users/me

Get current user profile

Requires authentication

---

PUT

/users/me

Update profile

---

PUT

/users/me/avatar

Update profile photo

---

PUT

/users/me/password

Change password (revokes all active sessions)

---

DELETE

/users/me

Delete account

---

GET

/users/me/listings

Current user listings

---

GET

/users/me/favorites

Current user favourites

---

GET

/users/me/reviews

Reviews received by current user

---

GET

/users/me/payments

Payment history for current user

---

GET

/users/me/subscriptions

Subscriptions for current user

---

GET

/users/me/notification-preferences

Get notification preferences

---

PUT

/users/me/notification-preferences

Update notification preferences

---

GET

/users/me/reports

Reports submitted by current user

---

GET

/users/{id}

Get public profile

---

GET

/users/{id}/listings

User listings

---

GET

/users/{id}/reviews

User reviews

---

# Categories

GET

/categories

Get all active categories (flat list). Supports `?locale=ar|en` or `Accept-Language`.

---

GET

/categories/tree

Get active category tree with nested children.

---

GET

/categories/{slug}

Get a single active category by slug.

---

GET

/categories/{id}/attributes

Dynamic fields (Phase 1E+)

---

# Locations

GET

/locations

Get all active locations (flat countries, cities, districts). Supports `?locale=ar|en` or `Accept-Language`.

---

GET

/locations/tree

Get nested location tree (country → city → district).

---

# Listings

**Phase 1E status:** Core listing CRUD, lifecycle actions, owner listing index, category attributes, and public browse/detail are implemented under `/api/v1`. **Phase 1F** implements listing image upload, reorder, and delete. Favourite, report, and admin moderation listing routes remain deferred.

GET

/listings

Get listings

Supports:

- Search
- Filters
- Pagination
- Sorting

---

GET

/listings/{id}

Get listing details

---

POST

/listings

Create listing

Authentication required

---

PUT

/listings/{id}

Update listing

---

DELETE

/listings/{id}

Delete listing

---

POST

/listings/{id}/submit

Submit draft for moderation

---

POST

/listings/{id}/pause

Pause listing

---

POST

/listings/{id}/activate

Reactivate paused listing

---

POST

/listings/{id}/mark-sold

Mark listing as sold

---

POST

/listings/{id}/renew

Renew expired listing

---

POST

/listings/{id}/archive

Archive listing

---

POST

/listings/{id}/restore

Restore archived listing

---

POST

/listings/{id}/images

Upload listing image

---

PUT

/listings/{id}/images/reorder

Reorder listing images

---

DELETE

/listings/{id}/images/{imageId}

Delete listing image

---

POST

/listings/{id}/favorite

Save listing to favourites

---

DELETE

/listings/{id}/favorite

Remove listing from favourites

---

GET

/listings/{id}/similar

Related listings

---

GET

/listings/featured

Featured listings

---

GET

/listings/latest

Latest listings

---

GET

/listings/popular

Popular listings

---

GET

/users/me/listings/{id}/statistics

Listing statistics for owner

---

# Promotions

At launch, only **Featured Listing** promotions are active. The promotion system is extensible for future types (homepage placement, urgent badge, top of search, automatic bump).

Purchasing a promotion uses the Payments module (`GET /payment-products`, `POST /payments/checkout`).

Launch promotion type:

- `featured` — Featured badge and boosted visibility

Future promotion types (documented, not active at launch):

- `homepage`
- `top_of_category`
- `top_of_search`
- `urgent`
- `highlight`
- `auto_bump`

---

# Search

GET

/search

Global search

Parameters

keyword

category

city

price_min

price_max

condition

sort

page

limit

---

GET

/search/suggestions

Autocomplete

---

GET

/search/popular

Popular searches

---

# Messages

GET

/conversations

User conversations

---

POST

/conversations

Create conversation

---

GET

/conversations/{id}

Conversation details

---

GET

/conversations/{id}/messages

Get messages

---

POST

/conversations/{id}/messages

Send message

---

POST

/conversations/{id}/read

Mark conversation as read

---

POST

/conversations/{id}/archive

Archive conversation

---

POST

/conversations/{id}/block

Block user in conversation

---

POST

/conversations/{id}/report

Report conversation

---

# Favourites

Favourites are managed via listing-scoped routes and the authenticated collection route.

GET

/users/me/favorites

My favourites

---

POST

/listings/{id}/favorite

Add favourite

---

DELETE

/listings/{id}/favorite

Remove favourite

---

# Reviews

POST

/reviews

Leave review

---

GET

/users/{id}/reviews

User reviews

---

PUT

/reviews/{id}

Update own review

---

DELETE

/reviews/{id}

Delete own review

---

POST

/reviews/{id}/report

Report review

---

GET

/admin/reviews

Admin review list

---

# Notifications

GET

/notifications

Get notifications

---

POST

/notifications/{id}/read

Mark one notification as read

---

POST

/notifications/read-all

Mark all as read

---

DELETE

/notifications/{id}

Delete notification

---

# Reports

Unified reporting endpoint. `entity_type` identifies the reported resource (listing, user, review, message, business).

POST

/reports

Create report

Body includes: entity_type, entity_id, reason, description, evidence (optional)

---

GET

/users/me/reports

Reports submitted by current user

---

GET

/admin/reports

Admin report queue

---

GET

/admin/reports/{id}

Report details

---

POST

/admin/reports/{id}/assign

Assign report to moderator

---

POST

/admin/reports/{id}/resolve

Resolve report

---

POST

/admin/reports/{id}/escalate

Escalate report

---

# Business Accounts

RESTful business resources replace legacy `/business/*` routes.

POST

/businesses

Register business account

---

GET

/businesses/{id}

Public business profile

---

PUT

/businesses/{id}

Update business profile (owner)

---

POST

/businesses/{id}/documents

Upload verification document

---

POST

/businesses/{id}/submit-verification

Submit business for verification

---

GET

/businesses/{id}/listings

Business listings

---

GET

/businesses/{id}/analytics

Business analytics

---

GET

/admin/businesses

Admin business list

---

POST

/admin/businesses/{id}/verify

Verify business

---

POST

/admin/businesses/{id}/reject

Reject business verification

---

POST

/admin/businesses/{id}/suspend

Suspend business

---

# Payments

GET

/payment-products

Available payment products (promotions, subscriptions, packages)

---

POST

/payments/checkout

Create checkout session (Stripe)

---

POST

/payments/webhook

Payment gateway callback (verified server-side)

---

GET

/payments/{id}

Payment details

---

GET

/users/me/payments

Payment history

---

POST

/admin/payments/{id}/refund

Admin refund (authorized roles)

---

# Subscriptions

GET

/payment-products

Available subscription plans (included in payment products)

---

GET

/users/me/subscriptions

Current user subscriptions

---

POST

/subscriptions/{id}/cancel

Cancel subscription

---

# Dashboard

GET

/dashboard

Dashboard overview

---

GET

/dashboard/statistics

Statistics

---

GET

/dashboard/messages

Recent messages

---

GET

/dashboard/favorites

Favourites

---

# Admin APIs

GET

/admin/dashboard

Admin overview

---

GET

/admin/users

User management list

---

GET

/admin/listings

Listing management list

---

GET

/admin/reports

Report queue

---

GET

/admin/categories

Category list

---

POST

/admin/categories

Create category

---

PUT

/admin/categories/{id}

Update category

---

DELETE

/admin/categories/{id}

Delete category

---

POST

/admin/categories/{id}/attributes

Create category attribute

---

PUT

/admin/category-attributes/{id}

Update category attribute

---

PUT

/admin/users/{id}/ban

Ban user

---

PUT

/admin/users/{id}/verify

Verify user

---

DELETE

/admin/listings/{id}

Delete listing (admin)

---

GET

/admin/analytics

Platform analytics

---

GET

/admin/payments

Payment records

---

GET

/admin/subscriptions

Subscription records

---

# Status Codes

200 OK

201 Created

204 No Content

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

429 Too Many Requests

500 Internal Server Error

---

# Pagination

Default

20 items

Maximum

100 items

Paginated responses use the unified envelope defined in Response Format (data + meta inside success envelope).

---

# Security

JWT Authentication (access + refresh tokens)

Role Based Access Control (RBAC)

Rate Limiting

Request Validation

Input Sanitization

HTTPS Only

Password Hashing

Refresh token rotation

Session revocation on critical account changes

Audit Logging

---

# API Standards

Use nouns instead of verbs.

Return consistent responses.

Never expose internal database structure.

Validate every request.

Return meaningful error messages.

Support pagination.

Support filtering.

Support sorting.

Support localization.

Maintain backward compatibility within the same API version.

---

# Post-MVP Core (Deferred)

The following endpoints are defined for future implementation. They are **not** part of MVP Core delivery. Database and architecture remain extensible for these features.

Saved searches and search history:

GET /users/me/searches

POST /users/me/searches

DELETE /users/me/searches/{id}

DELETE /users/me/search-history

Listing video:

POST /listings/{id}/video

Moderation appeals:

POST /moderation/appeals

Advanced promotion purchase endpoints beyond featured (additional product types via /payment-products).

Automated duplicate detection (internal; no public API required at launch).

---

# Future APIs

AI Recommendation API

AI Search API

Wallet

Coupons

Referral Program

Loyalty Points

Blog

Support Center

Mobile Push Notifications

Voice Search

Analytics API

Public API

Partner API

Webhook API

---

# Removed or Consolidated Routes

The following routes from v1.0 are removed or consolidated in v1.1:

| v1.0 route | v1.1 replacement |
|------------|------------------|
| GET /auth/profile | GET /auth/me (session) and GET /users/me (full profile) |
| PUT /auth/profile | PUT /users/me |
| GET /favorites | GET /users/me/favorites |
| DELETE /favorites/{listingId} | DELETE /listings/{id}/favorite |
| GET /reviews/user/{id} | GET /users/{id}/reviews |
| PUT /messages/{id}/read | POST /conversations/{id}/read |
| PUT /notifications/read-all | POST /notifications/read-all |
| POST /reports/listing | POST /reports (entity_type=listing) |
| POST /reports/user | POST /reports (entity_type=user) |
| POST /users/report | POST /reports (entity_type=user) |
| POST /business/register | POST /businesses |
| GET /business/profile | GET /businesses/{id} |
| PUT /business/profile | PUT /businesses/{id} |
| GET /business/listings | GET /businesses/{id}/listings |
| POST /payments/create | POST /payments/checkout |
| GET /payments/history | GET /users/me/payments |
| GET /subscriptions | GET /payment-products |
| POST /subscriptions/purchase | POST /payments/checkout |
| GET /subscriptions/current | GET /users/me/subscriptions |
| POST /listings/{id}/boost | POST /payments/checkout (featured promotion) |
| POST /listings/{id}/feature | POST /payments/checkout (featured promotion) |
| GET /search/trending | GET /search/popular |
