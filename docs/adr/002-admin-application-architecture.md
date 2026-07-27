# ADR-002: Admin Application Architecture

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Tamam requires an administration dashboard for moderators and administrators. The repository structure defines a separate `admin/` directory alongside `frontend/`.

Requirements from [PRODUCT_REQUIREMENTS.md](../PRODUCT_REQUIREMENTS.md):

- Admin dashboard with RBAC
- Moderation queues, user management, analytics, payments
- Granular permissions (view users, suspend users, approve refunds, etc.)

## Decision

Build the admin dashboard as a **separate Next.js 16 application** (App Router) in `/admin`.

Both `frontend` and `admin` use the same pinned stack: **Next.js 16**, **React 19**, **Node.js 22 LTS**, and **pnpm**.

### Structure

```
tamam/
├── frontend/     # Public marketplace (buyers, sellers)
├── admin/        # Internal operations dashboard
├── shared/       # Shared TypeScript types, API contracts, Zod schemas
└── backend/      # Laravel API (single backend for both apps)
```

### Shared package (`/shared`)

Both `frontend` and `admin` import from `@tamam/shared`:

- API response envelope types
- Pagination types
- Zod validation schemas for API contracts
- Shared constants (locales, promotion types)

### Authentication

- Admin app authenticates against the same `/api/v1` backend
- JWT access + refresh tokens (see [ADR-001](./001-authentication-jwt-refresh.md))
- Admin routes require RBAC permissions enforced server-side

### Deployment

- Admin may run on separate port in development (e.g. frontend `:3000`, admin `:3001`)
- Production may use subdomain (e.g. `admin.tamam.qa`) or path-based routing via reverse proxy

## Consequences

### Positive

- Clear separation between public UX and internal tools
- Independent deploy cadence for admin UI
- Smaller public frontend bundle
- Shared contracts prevent API drift

### Negative

- Two Next.js apps to maintain
- Some UI components may be duplicated unless extracted to shared later

### Mitigations

- Share types and validation via `/shared` from day one
- Extract shared UI primitives to `/shared` only when duplication becomes meaningful

## Related documents

- [API_SPEC.md](../API_SPEC.md) — Admin endpoints
- [UI_GUIDELINES.md](../UI_GUIDELINES.md) — Admin dashboard section
