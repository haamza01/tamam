# ADR-001: Authentication — JWT Access + Refresh Tokens

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Tamam requires secure authentication for web (frontend), admin dashboard, and future mobile clients. Documents previously referenced JWT, Laravel Sanctum, and session-based auth inconsistently.

Requirements from [PRODUCT_REQUIREMENTS.md](../PRODUCT_REQUIREMENTS.md):

- Register, login, logout, password reset
- Phone and email verification
- Rate limiting on auth endpoints
- Revoke sessions after password reset and critical account changes
- RBAC across user roles

## Decision

Use **JWT access tokens** with **refresh tokens** and **secure token rotation**.

### Access tokens

- Short-lived (recommended: 15 minutes)
- Sent in `Authorization: Bearer {token}` header
- Stateless validation where possible

### Refresh tokens

- Long-lived (recommended: 7–30 days, configurable)
- Stored **hashed** in `refresh_tokens` table (see [DATABASE.md](../DATABASE.md))
- Issued only over HTTPS
- Rotated on every refresh request (old token revoked, new token issued)

### Session revocation

All active refresh tokens for a user are revoked when:

- Password is reset or changed
- Email is changed (after re-verification flow initiates)
- Phone is changed (after re-verification flow initiates)
- Account is blocked, suspended, or deleted
- User explicitly logs out (current token only)
- Admin forces session revocation (future)

### Profile endpoints

- `GET /auth/me` — lightweight authenticated session check
- `GET /users/me` — full profile management (see [API_SPEC.md](../API_SPEC.md))

## Consequences

### Positive

- Works across separate frontend and admin apps
- Supports future mobile clients without cookie coupling
- Refresh rotation limits stolen refresh token window
- Aligns with API-first architecture

### Negative

- Requires careful refresh token storage and revocation logic
- Cannot instantly revoke access tokens before expiry without a denylist (optional future enhancement using Redis)

### Mitigations

- Keep access token lifetime short
- Maintain refresh token revocation in PostgreSQL
- Optional Redis denylist for compromised access tokens if needed later

## Related documents

- [API_SPEC.md](../API_SPEC.md) — Authentication endpoints
- [DATABASE.md](../DATABASE.md) — `refresh_tokens` table
- [SYSTEM_ARCHITECTURE.md](../SYSTEM_ARCHITECTURE.md) — Security section
