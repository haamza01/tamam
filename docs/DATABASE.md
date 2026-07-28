# DATABASE.md

# Tamam Marketplace Database Design

Version: 1.1

Database Engine: PostgreSQL

Source of Truth: Business behavior is defined in [PRODUCT_REQUIREMENTS.md](./PRODUCT_REQUIREMENTS.md). This document describes the data model aligned to those requirements.

Architecture decisions: See [docs/adr/](./adr/README.md).

---

# Database Philosophy

The database is designed for:

- Scalability
- High Performance
- Data Integrity
- Security
- Maintainability

The database must support millions of users and listings without requiring structural redesign.

---

# Naming Convention

## Tables

Plural

Examples:

users

listings

categories

messages

reviews

---

## Primary Key

id

UUID

Example

id UUID PRIMARY KEY

---

## Foreign Keys

user_id

listing_id

category_id

city_id

conversation_id

business_id

---

## Timestamps

created_at

updated_at

deleted_at

Soft delete whenever appropriate.

---

# Main Database Tables

## Users

Stores all platform users.

Fields

- id
- full_name
- username
- email
- phone
- password (hashed at application layer; never plain text)
- avatar
- bio
- language
- country
- city_id
- account_type
- verification_level
- status (pending, active, suspended, blocked, deleted)
- phone_verified_at
- email_verified_at
- trusted_seller (boolean; enables configurable auto-publish when allowed by platform settings)
- last_login
- created_at
- updated_at
- deleted_at

Notes

- v1.0 `password_hash` is renamed to `password` to follow Laravel conventions; hashing remains an application concern.
- Phone verification is required before publishing listings.

---

## Refresh Tokens

Stores hashed refresh tokens for JWT rotation.

Fields

- id
- user_id
- token_hash
- expires_at
- revoked_at
- created_at
- updated_at

Indexes

- user_id
- token_hash
- expires_at

Rules

- All rows for a user are revoked on password reset and critical account changes.
- Tokens are never stored in plain text.

---

## User Verifications

Stores identity verification records.

Fields

- id
- user_id
- document_type
- document_number
- document_image
- verification_status
- verified_at
- created_at
- updated_at

---

## Roles

RBAC role definitions.

Fields

- id
- name (guest, user, verified_user, business, moderator, admin, super_admin)
- slug
- description
- created_at
- updated_at

---

## Permissions

Granular permission definitions.

Fields

- id
- name
- slug
- group
- created_at
- updated_at

---

## Role Permission

Pivot: roles to permissions.

Fields

- role_id
- permission_id

---

## User Role

Pivot: users to roles.

Fields

- user_id
- role_id

---

## Businesses

Company accounts.

Fields

- id
- owner_id
- company_name
- commercial_license
- logo
- website
- email
- phone
- description
- address
- city_id
- status (draft, pending_verification, verified, rejected, suspended)
- verified_at
- created_at
- updated_at
- deleted_at

---

## Business Documents

Private verification documents for businesses.

Fields

- id
- business_id
- document_type
- file_path
- status
- created_at
- updated_at

---

## Countries

Countries.

Fields

- id (uuid)
- code (unique, e.g. QA)
- slug (unique)
- sort_order
- is_active
- created_at
- updated_at

Notes

- Localized names are stored in `country_translations`.

---

## Country Translations

Fields

- id (uuid)
- country_id
- locale (ar, en)
- name
- created_at
- updated_at

Unique

- country_id + locale

---

## Cities

Cities.

Fields

- id (uuid)
- country_id
- slug
- sort_order
- is_active
- created_at
- updated_at

Unique

- country_id + slug

Notes

- Localized names are stored in `city_translations`.

---

## City Translations

Fields

- id (uuid)
- city_id
- locale (ar, en)
- name
- created_at
- updated_at

Unique

- city_id + locale

---

## Districts

Optional districts within cities (Qatar launch locations).

Fields

- id (uuid)
- city_id
- slug
- sort_order
- is_active
- created_at
- updated_at

Unique

- city_id + slug

Notes

- Localized names are stored in `district_translations`.

---

## District Translations

Fields

- id (uuid)
- district_id
- locale (ar, en)
- name
- created_at
- updated_at

Unique

- district_id + locale

---

## Categories

Marketplace categories.

Fields

- id
- parent_id
- slug
- icon
- image
- sort_order
- status (active, hidden, archived)
- seo_title
- seo_description
- listing_count (cached; **maintained in Phase 1E+** — incremented/decremented for `published` listings on leaf categories)
- created_at
- updated_at
- deleted_at

Supports hierarchical nesting up to `category_max_depth` (default 3, configurable in platform settings).

Notes

- Localized names are stored in `category_translations`.
- v1.0 single `name` field is replaced by translation rows for Arabic and English.

---

## Category Translations

Localized category names and descriptions.

Fields

- id
- category_id
- locale (ar, en)
- name
- description
- created_at
- updated_at

Unique

- category_id + locale

---

## Category Attributes

Dynamic fields.

Examples

Car Brand

Mileage

Bedrooms

Area

Year

Condition

Salary

Fields

- id
- category_id
- slug
- type (text, long_text, number, price, dropdown, radio, checkbox, boolean, date, multi_select)
- required
- searchable
- filterable
- sort_order
- unit
- min_value
- max_value
- validation_rules (json)
- created_at
- updated_at

Notes

- Localized attribute labels are stored in `category_attribute_translations`.
- Select options are stored in `category_attribute_options`.

---

## Category Attribute Translations

Fields

- id
- category_attribute_id
- locale
- name
- created_at
- updated_at

---

## Category Attribute Options

Dropdown, radio, and multi-select options.

Fields

- id
- category_attribute_id
- value
- sort_order
- created_at
- updated_at

---

## Category Attribute Option Translations

Fields

- id
- category_attribute_option_id
- locale
- label
- created_at
- updated_at

---

## Listings

Main marketplace table.

**Phase 1E implementation notes:** Implemented without `business_id`, `listing_type`, or direct `country_id` (country resolved via `city_id`). Price is `decimal(12,2)` with a PostgreSQL non-negative CHECK. Optimistic concurrency via `version`. Soft deletes enabled. Listing images implemented in Phase 1F (`listing_images` table).

Fields

- id
- user_id
- business_id (nullable)
- category_id
- city_id
- district_id (nullable)
- title
- slug
- description
- price
- price_type (fixed, negotiable, free, contact_for_price)
- currency (default QAR)
- listing_type
- condition
- status (draft, pending_review, published, rejected, paused, sold, expired, archived, blocked, deleted)
- rejection_reason
- moderation_notes (internal)
- latitude (nullable)
- longitude (nullable)
- contact_preferences (json)
- featured (boolean; active featured promotion)
- expires_at
- published_at
- sold_at
- search_vector (tsvector, **Phase 1G** — GENERATED STORED; see PHASE_1G.md)
- Index: listings_search_vector_idx (GIN)
- Index: listings_published_title_prefix_idx (partial prefix on lower(title))
- created_at
- updated_at
- deleted_at

Notes

- v1.0 `premium` flag is replaced by `listing_promotions` for extensible promotion types.
- v1.0 aggregate counters move to `listing_statistics`.

---

## Listing Attribute Values

Dynamic attribute values per listing.

Fields

- id
- listing_id
- category_attribute_id
- value_text
- value_number
- value_boolean
- value_date
- value_json
- created_at
- updated_at

Unique

- listing_id + category_attribute_id

---

## Listing Images

Stores listing gallery metadata. Binary data lives in object storage only (no URLs stored as canonical data).

**Phase 1F implementation notes:** Hard delete (no soft deletes). Object keys stored; public URLs derived at runtime via `PUBLIC_ASSETS_URL`. Source uploads stored on private `local` disk until processing completes.

Fields

- id (UUID)
- listing_id (UUID, FK → listings, cascade delete)
- original_object_key (nullable; private source path `{prefix}/{listing_id}/{image_id}/source`)
- processed_object_key (nullable; public `{prefix}/{listing_id}/{image_id}/original.webp`)
- thumbnail_object_key (nullable; public `{prefix}/{listing_id}/{image_id}/thumb.webp`)
- mime_type
- original_width, original_height
- processed_width, processed_height
- file_size (bytes)
- sort_order (0-based; first ready image = cover)
- status (`pending`, `processing`, `ready`, `failed`)
- processing_error_code (nullable; safe stable code for owner API)
- created_at, updated_at

Unique

- listing_id + sort_order

Indexes

- listing_id + status
- status + updated_at

Constraints (PostgreSQL)

- sort_order ≥ 0
- file_size, width, height ≥ 0 when not null

---

## Listing Videos

Optional video (Post-MVP Core implementation; schema reserved).

Fields

- id
- listing_id
- video_url
- status (processing, ready, failed)
- duration_seconds
- created_at
- updated_at

---

## Listing Statistics

Aggregated listing metrics.

Fields

- id
- listing_id
- views_count
- unique_views_count
- favorites_count
- messages_count
- phone_reveal_count
- whatsapp_click_count
- share_count
- promotion_impressions
- promotion_clicks
- updated_at

---

## Favorites

Stores saved ads (Phase 1H).

Fields

- id (UUID, PK)
- user_id (UUID, FK → users, **cascade on delete**)
- listing_id (UUID, FK → listings, **cascade on delete**)
- created_at
- updated_at

Unique

- user_id + listing_id

Indexes

- unique `(user_id, listing_id)`
- btree on `listing_id`

Foreign-key behaviour

- **User deleted:** favourite rows cascade-delete; application decrements `listing_statistics.favorites_count` before user removal where applicable, otherwise cascade removes rows when listing is also removed.
- **Listing hard-deleted:** favourite rows cascade-delete with listing; `listing_statistics` row also cascade-deletes.
- **Listing soft-deleted:** favourite rows retained; excluded from authenticated favourites list via public visibility rules.

Migration: `2026_07_28_600001_create_favorites_table.php`

---

## Conversations

Private chats.

Fields

- id
- listing_id
- buyer_id
- seller_id
- last_message_at
- buyer_archived_at
- seller_archived_at
- blocked_by_user_id (nullable)
- listing_snapshot (json)
- created_at
- updated_at

---

## Messages

Chat messages.

Fields

- id
- conversation_id
- sender_id
- message
- message_type (text, listing_reference, system, image, location, document)
- is_read
- created_at
- updated_at

---

## User Blocks

User-level blocks for messaging safety.

Fields

- id
- blocker_id
- blocked_id
- created_at
- updated_at

Unique

- blocker_id + blocked_id

---

## Reviews

User ratings.

Fields

- id
- reviewer_id
- reviewed_user_id
- listing_id (nullable)
- conversation_id (nullable)
- rating
- comment
- tags (json)
- status (visible, hidden, removed)
- created_at
- updated_at
- deleted_at

---

## Reports

Polymorphic reports.

Fields

- id
- reporter_id
- entity_type (listing, user, review, message, business)
- entity_id
- reason
- description
- evidence (json)
- status (new, under_review, awaiting_information, resolved, rejected, escalated)
- assigned_moderator_id
- resolution
- internal_notes
- created_at
- updated_at

Notes

- v1.0 listing-only reports table is extended to polymorphic reporting.

---

## Moderation Actions

Audit trail for moderation decisions.

Fields

- id
- actor_id
- action
- entity_type
- entity_id
- reason
- previous_state (json)
- new_state (json)
- internal_note
- created_at

---

## Moderation Appeals

Post-MVP Core implementation; schema reserved.

Fields

- id
- user_id
- moderation_action_id
- reason
- status (pending, approved, rejected)
- reviewed_by
- reviewed_at
- created_at
- updated_at

---

## Notifications

Push and in-app notifications.

Fields

- id
- user_id
- title
- body
- type
- data (json)
- is_read
- read_at
- created_at
- updated_at

---

## Notification Preferences

Per-user notification channel preferences.

Fields

- id
- user_id
- category
- in_app_enabled
- email_enabled
- push_enabled (future)
- created_at
- updated_at

Unique

- user_id + category

---

## Payment Products

Promotions, subscriptions, and packages.

Fields

- id
- type (promotion, subscription, listing_package, renewal_package, business_package)
- slug
- name
- description
- price
- currency
- duration_days
- features (json)
- promotion_type (nullable; featured at launch)
- active
- created_at
- updated_at

Launch scope

- Only `promotion_type = featured` is active at launch.

---

## Orders

Order records.

Fields

- id
- user_id
- business_id (nullable)
- product_id
- listing_id (nullable)
- amount
- currency
- status
- created_at
- updated_at

---

## Payments

Stores payment history.

Fields

- id
- user_id
- order_id
- amount
- currency
- payment_method
- payment_status
- provider
- provider_reference
- transaction_reference
- created_at
- updated_at

---

## Subscriptions

Premium plans.

Fields

- id
- user_id
- package_id
- start_date
- end_date
- auto_renew
- status
- created_at
- updated_at

---

## Subscription Packages

Premium plans.

Fields

- id
- name
- duration
- price
- features (json)
- created_at
- updated_at

---

## Listing Promotions

Active and historical listing promotions.

Fields

- id
- listing_id
- order_id
- promotion_type (featured at launch)
- status
- starts_at
- ends_at
- impressions
- clicks
- created_at
- updated_at

---

## Advertisement Packages

Legacy reference from v1.0.

New implementations should use `payment_products` and `listing_promotions`.

Fields

- id
- name
- duration
- price

---

## Platform Settings

Configurable platform rules.

Fields

- id
- key
- value (json)
- group
- description
- created_at
- updated_at

Examples

- default_listing_duration_days
- require_manual_moderation_for_new_users
- auto_publish_for_trusted_users

---

## Content Pages

Static content pages.

Fields

- id
- slug
- locale
- title
- body
- status
- created_at
- updated_at

---

## Audit Logs

Tracks important actions.

Fields

- id
- user_id
- action
- entity
- entity_id
- ip_address
- user_agent
- metadata (json)
- created_at

---

# Relationships

One User → Many Listings

One User → Many Messages

One User → Many Refresh Tokens

One Business → Many Listings

One Listing → Many Images

One Listing → Many Attribute Values

One Listing → Many Promotions

One Conversation → Many Messages

One Category → Many Listings

One Category → Many Attributes

One User → Many Reviews

One Order → Payments

---

# Indexing Strategy

Create indexes for

email

phone

username

slug

category_id

city_id

district_id

user_id

business_id

status

featured

created_at

price

published_at

expires_at

entity_type + entity_id

listing_id + promotion_type

token_hash

locale

---

# Security Rules

Passwords are hashed.

Never store plain text passwords.

Never store plain text OTP or refresh tokens.

Sensitive information must be encrypted.

Use UUIDs instead of incremental IDs.

Never expose internal IDs through APIs when avoidable.

---

# Post-MVP Core Tables (Deferred Implementation)

## Saved Searches

- id, user_id, name, query (json), notify_enabled, created_at, updated_at

## Search History

- id, user_id, query, filters (json), created_at

## Duplicate Detection Signals

- id, listing_id, matched_listing_id, signal_type, score, status, created_at

---

# Future Tables

AI Recommendations

Recently Viewed

Coupons

Referral System

Wallet

Loyalty Points

Support Tickets

Blogs

Announcements

Feature Flags

Analytics Events

Abuse Detection

Machine Learning Models

OAuth Providers

Device History

Activity Timeline

---

# Database Principles

Normalize data.

Avoid duplicate information.

Prefer lookup tables.

Always use foreign keys.

Always use transactions when modifying related data.

Optimize for read performance without sacrificing integrity.

Every table must have id, created_at, updated_at.

Soft delete where applicable.

No orphan records.

No duplicated business logic inside the database.

Business logic belongs to the application layer.
