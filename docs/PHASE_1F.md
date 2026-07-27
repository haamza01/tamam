# Phase 1F — Listing images & MinIO

**Status:** Complete  
**Branch:** `phase/1f-listing-images-minio`  
**Depends on:** Phase 1E (`v0.5.0-phase1e`)

## Documentation review and resolved ambiguities

| Topic | Source conflict | Phase 1F resolution |
|-------|-----------------|---------------------|
| `listing_images.status` | DATABASE.md lacked status column | Added `pending`, `processing`, `ready`, `failed` (aligned with technical design §12 and video table pattern) |
| Row creation timing | Design diagram shows job creating row | Create DB row synchronously with `status=pending`; dispatch job **after commit** |
| Submit minimum | “≥1 image” | Require ≥1 image in **`ready`** state (pending/failed do not count) |
| Object keys | DATABASE.md listed `image_url` | Store **object keys** only; URLs derived via `PublicAssetUrlResolver` + `PUBLIC_ASSETS_URL` |
| Source storage | Design says “temp” | Private **`local`** disk at `{prefix}/{listing_id}/{image_id}/source`; deleted after successful processing |
| Processed filenames | Design §12.2 | Public variants: `original.webp`, `thumb.webp` under `listings/{listing_id}/{image_id}/` |
| Soft delete on images | Not documented | **Hard delete** with storage cleanup |
| Presigned uploads | Not in scope | **Not implemented** — server-side multipart upload only |
| HTTP status on upload | Design allows 202 or 201 | **202 Accepted** with owner resource including processing status |
| Image library | Intervention Image v3 | GD driver (Docker `php:8.4-cli` + `gd` extension) |

## Scope delivered

### Database
- `listing_images` migration with UUID PK, FK cascade on listing delete, unique `(listing_id, sort_order)`, indexes on `(listing_id, status)` and `(status, updated_at)`, PostgreSQL CHECK constraints

### Storage
- Source uploads on private `local` disk
- Processed/thumbnail variants on `public_assets` (MinIO/S3) with scoped bucket policy for `avatars/*` and `listings/*`
- `PublicAssetUrlResolver` for public URLs (never exposes internal MinIO hostname)

### Processing
- `ProcessListingImageJob` on `media` queue — 3 tries, backoff `[30, 60, 120]`, timeout 120s, `ShouldBeUnique`
- Intervention Image v3 (GD): EXIF orientation, metadata strip, max width 1920 (no upscaling), thumbnail 400px, WebP quality 82
- Idempotent retries; stale `processing` recovery after 5 minutes

### API endpoints

| Method | Path | Auth | Middleware | Purpose |
|--------|------|------|------------|---------|
| POST | `/api/v1/listings/{id}/images` | Owner | `auth:api`, `account.active`, `phone.verified`, `throttle:listing-image` | Upload image (202) |
| PUT | `/api/v1/listings/{id}/images/reorder` | Owner | same | Full reorder via `{ image_ids: [uuid, ...] }` |
| DELETE | `/api/v1/listings/{id}/images/{imageId}` | Owner | same | Delete image + storage cleanup |

### Submit rule
- `ListingImageService::assertSubmitMinimum()` — at least one **`ready`** image required on submit (including trusted-seller auto-publish)

### Maintenance
- `listings:cleanup-orphan-images` — dry-run supported; removes stale source objects without DB rows and old pending/failed rows; scheduled daily

### Audit events
- `listing.image.uploaded`
- `listing.image.processing_succeeded`
- `listing.image.processing_failed`
- `listing.images.reordered`
- `listing.image.deleted`

## Image schema

See `docs/DATABASE.md` § Listing Images.

## Upload limits and accepted formats

| Limit | Value |
|-------|-------|
| Max images per listing | 20 |
| Max upload size | 10 MB (10240 KB) |
| Allowed MIME (magic bytes) | JPEG, PNG, WebP |
| Allowed extensions (secondary) | jpg, jpeg, png, webp |
| Max dimension | 8000 px (width or height) |
| Max pixel count | 40,000,000 |
| Rejected | SVG, GIF, animated formats, spoofed MIME |

Configurable via `config/media.php` and env vars (`LISTING_IMAGE_*`).

## Processing states

| Status | Description |
|--------|-------------|
| `pending` | Row created; source stored; job queued |
| `processing` | Worker owns the image |
| `ready` | Variants stored; source removed |
| `failed` | Safe `processing_error_code` set for owner |

### Transition matrix

| From \ To | pending | processing | ready | failed |
|-----------|---------|------------|-------|--------|
| pending | — | ✓ | — | ✓ |
| processing | — | — | ✓ | ✓ |
| ready | — | — | — | — |
| failed | — | ✓ (retry) | — | — |

## Object-key structure

```
listings/{listing_id}/{image_id}/source          (private, until processed)
listings/{listing_id}/{image_id}/original.webp   (public)
listings/{listing_id}/{image_id}/thumb.webp      (public)
```

## Public/private storage policy

- **Private:** source originals on `local` disk during processing
- **Public:** only `original.webp` and `thumb.webp` on `public_assets` after successful processing
- URLs built as `{PUBLIC_ASSETS_URL}/{object_key}` — no canonical URLs in PostgreSQL

## Generated variants

| Variant | Max width | Format | Upscaling |
|---------|-----------|--------|-----------|
| Display (`original.webp`) | 1920 px | WebP 82 | No |
| Thumbnail (`thumb.webp`) | 400 px | WebP 82 | No |

## Upload flow

1. Authorise listing ownership (`ListingPolicy::update`)
2. Validate listing state permits editing
3. Validate file (size, MIME magic, dimensions, decompression bomb)
4. Transaction: lock listing, assign `sort_order`, create `listing_images` row (`pending`), store source
5. Dispatch `ProcessListingImageJob` **after commit**
6. Return 202 with owner `ListingImageResource`

## Queue configuration

| Setting | Value |
|---------|-------|
| Queue name | `media` |
| Tries | 3 |
| Backoff (seconds) | 30, 60, 120 |
| Timeout | 120 s |
| Uniqueness | Per `listing_image_id` |

## Ordering and cover image

- `sort_order` is 0-based; assigned sequentially on upload
- Reorder requires **full** image ID list for the listing (no partial reorder)
- Two-phase reorder avoids unique constraint violations on `(listing_id, sort_order)`
- First **ready** image by `sort_order` is the cover (`ListingCardResource.cover_image`)

## Deletion semantics

1. Lock listing row
2. Lock and hard-delete image row; compact higher `sort_order` values
3. Delete all related storage objects (source + processed + thumbnail)
4. Running workers check row existence / status before writing variants

Repeated delete on missing image: idempotent (404 from route model binding if already gone).

## Listing soft-delete interaction

- Listing soft-delete does **not** remove image rows (retained for potential future restore/purge policy)
- Public listing endpoints stop returning image URLs when listing is not publicly visible
- Listing hard delete (cascade) removes `listing_images` rows; storage cleanup via delete flow or orphan command

## Minimum image requirement on submit

At least **one image in `ready` state**. Pending and failed images do not satisfy the rule. Error code: `listing.image_required` on field `images`.

## Error codes (stable)

| Code | When |
|------|------|
| `listing.image_required` | Submit without ready image |
| `listing.image_limit_reached` | More than 20 images |
| `listing.image_invalid_type` | Bad MIME/extension/content |
| `listing.image_too_large` | Over 10 MB |
| `listing.image_dimensions_invalid` | Over max dimension/pixels |
| `listing.image_not_found` | Reorder/delete foreign ID |
| `listing.image_reorder_incomplete` | Reorder missing IDs |
| `listing.image_reorder_duplicate` | Duplicate IDs in reorder |
| `listing.image_processing_failed` | Worker decode/process failure |
| `listing.image_source_missing` | Source object missing at process time |
| `listing.not_editable` | Wrong listing state or non-owner |

## Rate limiting

`listing-image` limiter: **10 requests/minute** per user (upload, reorder, delete).

## Environment variables

See `backend/.env.example`: `LISTING_IMAGE_DISK`, `LISTING_IMAGE_SOURCE_DISK`, `LISTING_IMAGE_MAX_KB`, `LISTING_IMAGE_MAX_COUNT`, `LISTING_IMAGE_MAX_WIDTH`, `LISTING_IMAGE_THUMBNAIL_WIDTH`, `LISTING_IMAGE_WEBP_QUALITY`, `LISTING_IMAGE_MAX_PIXELS`, `LISTING_IMAGE_MAX_DIMENSION`, plus existing `PUBLIC_ASSETS_URL`, `AWS_*`, `STORAGE_PROVISION_BUCKETS`.

## Docker/runtime dependencies

- `intervention/image` ^3.11
- PHP GD extension with JPEG, PNG, WebP support (`docker/backend/Dockerfile`)
- Queue worker must consume `media` queue in production

## Deferred / out of scope

- Presigned/direct-to-S3 client uploads
- Frontend image uploader
- Video/audio/document uploads
- Image moderation AI, crop/rotate endpoints, separate set-cover endpoint
- CDN beyond `PUBLIC_ASSETS_URL`
- Phase 1G+ work

## Tests

`tests/Feature/Listing/ListingImageTest.php` plus updated Phase 1E lifecycle tests (submit helpers upload a ready image first).
