# Phase 1B — Authentication

**Status:** Complete  
**Branch:** `phase/1b-authentication`  
**Depends on:** Phase 1A (merged to `main`)

## Scope delivered

- Individual user registration with Qatar phone normalisation
- Login by phone or email + password (JWT access token in JSON body)
- Refresh token table, rotation, reuse detection, and revocation
- Secure httpOnly refresh cookie + double-submit CSRF for `/auth/refresh`
- Phone OTP request/verify (Cache/Redis-backed storage, `LogOtpProvider` in local/testing)
- Password reset via OTP (account enumeration protection)
- Current user, logout, logout-all, and refresh endpoints
- Auth audit events with sanitised metadata
- Rate limiting on all auth endpoints
- Comprehensive feature tests

## Endpoints

| Method | Path | Auth | Rate limit |
|--------|------|------|------------|
| POST | `/api/v1/auth/register` | No | 5/min per IP |
| POST | `/api/v1/auth/login` | No | 5/min per IP |
| POST | `/api/v1/auth/refresh` | Cookie + CSRF | 20/min per IP |
| GET | `/api/v1/auth/me` | Bearer JWT | — |
| POST | `/api/v1/auth/logout` | Bearer JWT | — |
| POST | `/api/v1/auth/logout-all` | Bearer JWT | — |
| POST | `/api/v1/auth/verify-phone` | Bearer JWT | 10/min per user |
| POST | `/api/v1/auth/resend-phone-code` | Bearer JWT | 3/hour per user |
| POST | `/api/v1/auth/forgot-password` | No | 3/hour per IP+identifier |
| POST | `/api/v1/auth/reset-password` | No | 3/hour per IP+identifier |

## Token & cookie configuration

| Setting | Env / default | Notes |
|---------|---------------|-------|
| Access token TTL | `JWT_TTL` / `JWT_ACCESS_TTL` (15 min) | Returned in JSON only; client holds in memory |
| Refresh token TTL | `JWT_REFRESH_TTL_DAYS` (14 days) | Stored hashed in DB; raw value in cookie only |
| Refresh cookie | `AUTH_REFRESH_COOKIE` → `tamam_refresh_token` | httpOnly, path `/api/v1/auth` |
| CSRF cookie | `AUTH_CSRF_COOKIE` → `tamam_auth_csrf` | Not httpOnly; path `/api/v1/auth` |
| Cookie Secure | `AUTH_COOKIE_SECURE` (true in production) | Always Secure in production |
| SameSite | `AUTH_COOKIE_SAME_SITE` (`lax`) | Suitable for same-site SPA/API |
| CSRF header | `X-Auth-CSRF` | Must match CSRF cookie on refresh (see cookie encryption below) |

### Cookie encryption

Authentication cookies **remain encrypted** by Laravel's `EncryptCookies` middleware. Exclusion is **not required** for the refresh flow. The middleware is prepended to the `api` group because Laravel's default API stack does not include it (unlike `web`).

| Cookie | Encryption | Why it still works |
|--------|------------|-------------------|
| `tamam_refresh_token` | Yes (httpOnly) | Browser sends the encrypted value; middleware decrypts before the refresh token is hashed and validated. JavaScript never reads this cookie. |
| `tamam_auth_csrf` | Yes (readable by JS) | Follows the Laravel XSRF pattern: client-side code reads the encrypted cookie value from `document.cookie` and sends it in `X-Auth-CSRF`; `ValidateRefreshCsrf` decrypts the header and compares it to the decrypted cookie value. |

**Security implications of encryption enabled (preferred):**

- Cookie payloads are signed and encrypted with `APP_KEY`, adding defence-in-depth even though refresh tokens are opaque random strings and CSRF tokens are random.
- A stolen database or log line still does not reveal raw refresh tokens (only SHA-256 hashes are stored server-side).
- Client integrations must copy the CSRF cookie value verbatim into `X-Auth-CSRF` (encrypted form in production), not a separately cached plain token.

**Previous exclusion (removed):** Cookies were briefly excluded during development to simplify PHPUnit cookie assertions. That was a testing convenience, not a security requirement. Production and tests now use Laravel encryption end-to-end.

## OTP configuration

| Setting | Default |
|---------|---------|
| Length | 6 digits |
| Expiry | 300 seconds |
| Max verify attempts | 5 |
| Resend cooldown | 60 seconds |
| Storage | Laravel Cache (Redis in production via `CACHE_STORE=redis`) |

OTPs are never returned in API responses. `LogOtpProvider` writes codes to the application log only in `local`/`testing`.

## Auth error codes

| Code | HTTP | When |
|------|------|------|
| `auth.invalid_credentials` | 401 | Wrong phone/email or password |
| `auth.account_blocked` | 403 | Blocked account login |
| `auth.account_suspended` | 403 | Suspended account login |
| `auth.account_deleted` | 403 | Soft-deleted account login |
| `auth.phone_taken` | 422 | Duplicate phone on register |
| `auth.email_taken` | 422 | Duplicate email on register |
| `auth.csrf_invalid` | 403 | Missing/mismatched CSRF on refresh |
| `auth.refresh_invalid` | 401 | Unknown refresh token |
| `auth.refresh_expired` | 401 | Expired refresh token |
| `auth.refresh_reused` | 401 | Revoked token reused (all sessions revoked) |
| `auth.otp_expired` | 422 | OTP expired |
| `auth.otp_invalid` | 422 | Wrong OTP |
| `auth.otp_attempts_exceeded` | 429 | Too many OTP attempts |
| `auth.otp_cooldown` | 429 | Resend too soon |

## Database changes

- `users.email` nullable with unique constraint
- New `refresh_tokens` table (`user_id`, `token_hash`, `expires_at`, `revoked_at`)

## Audit events

- `auth.registered`
- `auth.login.succeeded` / `auth.login.failed`
- `auth.logout` / `auth.logout.all`
- `auth.phone.verified`
- `auth.password.reset`
- `auth.refresh.reuse_detected`
- `auth.tokens.revoked`

Metadata is sanitised; tokens, passwords, OTPs, and cookies are never logged.

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
pnpm typecheck
pnpm lint
pnpm build
```

## Out of scope (Phase 1C+)

- Profiles, avatars, categories, listings, images, search, favorites, messaging, reports, notifications
- Frontend/admin auth UI, production SMS, social login, email verification
