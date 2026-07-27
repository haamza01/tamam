# Phase 1C — Profiles & Avatars

**Status:** Complete  
**Branch:** `phase/1c-profiles`  
**Depends on:** Phase 1B (merged to `main`)

## Scope delivered

- Authenticated profile read and partial update (PATCH semantics)
- Avatar upload, replacement, and deletion
- MinIO/S3-compatible storage via Laravel `public_assets` disk
- Image validation (MIME, extension, size, magic bytes via `getimagesize`)
- Profile and avatar audit events with sanitised metadata
- Rate limiting on profile update and avatar upload
- Comprehensive feature tests

## Endpoints

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| GET | `/api/v1/profile` | Bearer JWT + active account | — |
| PATCH | `/api/v1/profile` | Bearer JWT + active account | 10/min per user |
| POST | `/api/v1/profile/avatar` | Bearer JWT + active account | 5/min per user |
| DELETE | `/api/v1/profile/avatar` | Bearer JWT + active account | — |

All endpoints return the authenticated user's own profile only.

## Editable fields

| API field | DB column | Notes |
|-----------|-----------|-------|
| `full_name` | `full_name` | Max 100 characters |
| `email` | `email` | Optional; must remain unique |
| `preferred_language` | `language` | `ar` or `en` |
| `username` | `username` | Optional; alphanumeric + underscore; max 30 |
| `bio` | `bio` | Optional; max 500 characters |
| `avatar` | — | Via dedicated upload/delete endpoints only |

## Protected fields (not editable)

- `phone` (phone change flow deferred)
- `password`, `status`, `account_type`, `verification_level`
- `phone_verified_at`, `email_verified_at`, `trusted_seller`
- `country_id`, `city_id` (locations API deferred to Phase 1D)
- Roles, permissions, and audit metadata

## Avatar rules

| Setting | Value |
|---------|-------|
| Max size | 5 MB (`AVATAR_MAX_KB=5120`) |
| Allowed types | JPEG, PNG, WebP |
| Storage disk | `public_assets` (MinIO/S3 in dev/prod, local in tests) |
| Object key pattern | `avatars/{user_id}/{uuid}.{ext}` |
| Default fallback | `AVATAR_DEFAULT_URL` (`/images/default-avatar.svg`) |
| Processing | None in Phase 1C (no Intervention Image; listing resize deferred to Phase 1F) |

Upload flow stores the new object first, updates the database, then deletes the previous avatar. Failed storage does not remove the existing avatar.

## Profile error codes

| Code | HTTP | When |
|------|------|------|
| `profile.email_taken` | 422 | Duplicate email |
| `profile.username_taken` | 422 | Duplicate username |
| `profile.field_protected` | 422 | Attempt to modify a protected field |
| `profile.avatar_invalid` | 422 | Invalid or corrupted image |
| `profile.avatar_invalid_type` | 422 | Disallowed MIME/extension |
| `profile.avatar_too_large` | 422 | Exceeds size limit |
| `profile.avatar_storage_failed` | 500 | Storage write failure |

## Audit events

- `profile.updated` (metadata: changed field names only)
- `profile.avatar.uploaded`
- `profile.avatar.deleted`

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
pnpm typecheck
pnpm lint
pnpm build
```

## Out of scope (Phase 1D+)

- Public seller profiles, phone change, email verification
- Locations/category APIs, listings, listing images, search, messaging, notifications
- Frontend profile UI, admin profile management
