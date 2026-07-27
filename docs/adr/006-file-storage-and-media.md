# ADR-006: File Storage and Media Processing

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Tamam requires secure file uploads for listing images, profile avatars, and business verification documents. [SYSTEM_ARCHITECTURE.md](../SYSTEM_ARCHITECTURE.md) specifies S3-compatible storage with image processing (resize, compress, WebP, thumbnails).

Deferred post-MVP Core: **listing video uploads**.

## Decision

### Storage backend

- **Production:** S3-compatible object storage (AWS S3, Cloudflare R2, or DigitalOcean Spaces)
- **Development:** MinIO via Docker Compose
- Laravel Filesystem abstraction; no direct cloud SDK calls in domain layer

### Upload flow

1. Client sends file to API (multipart)
2. Server validates type, size, and MIME
3. Store temporarily if needed for processing
4. Process images asynchronously via Laravel Queue where heavy
5. Upload final assets to object storage
6. Persist URL references in database (`listing_images`, user `avatar`, `business_documents`)

### Image processing

| Asset | Max size | Formats | Processing |
|-------|----------|---------|------------|
| Listing image | 10 MB | JPG, PNG, WEBP | Resize, compress, WebP conversion, thumbnail |
| Profile avatar | 5 MB | JPG, PNG, WEBP | Compress, thumbnail |
| Business document | TBD | PDF, JPG, PNG | Store as-is; private bucket |

### Listing video (deferred)

- Schema reserved in `listing_videos` table
- `POST /listings/{id}/video` documented as Post-MVP Core in [API_SPEC.md](../API_SPEC.md)
- When implemented: MP4/MOV, max 60 seconds, 100 MB, async processing

### Security

- Private bucket for verification documents; signed URLs for authorized access only
- Public bucket/CDN for listing images and avatars
- Virus scanning marked as future in architecture doc

## Consequences

### Positive

- Scalable storage independent of application servers
- CDN-friendly public assets
- Deferred video reduces MVP Core complexity

### Negative

- Requires MinIO/S3 setup in dev and production
- Async processing adds queue dependency for optimal UX

## Related documents

- [DATABASE.md](../DATABASE.md) — listing_images, listing_videos, business_documents
- [SYSTEM_ARCHITECTURE.md](../SYSTEM_ARCHITECTURE.md) — File Upload Flow
