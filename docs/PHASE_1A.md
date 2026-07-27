# Phase 1A — Foundation & RBAC

**Status:** Complete  
**Branch:** `phase/1a-foundation-rbac`  
**Depends on:** Phase 0.5 approved technical design

## Scope delivered

- UUID primary key foundation (`HasUuid` trait)
- Marketplace `users` table restructure (Phase 1 fields)
- Roles and permissions tables with seed data (`user`, `moderator`, `admin`, `super_admin`)
- `HasRoles` concern, permission middleware, gates, and `UserPolicy` foundation
- Platform settings service, seeder, and cache layer
- Audit log service and table
- OTP provider abstraction (`OtpProviderInterface`) with development `LogOtpProvider`
- Prohibited words config + checker (DB-ready moderation foundation)
- JWT package installed after compatibility verification (Laravel 12 + PHP 8.4 + ext-sodium)
- JWT guard configured; no authentication HTTP endpoints (Phase 1B)

## Migrations (Phase 1A)

1. `create_roles_table`
2. `create_permissions_table`
3. `create_role_permission_table`
4. `create_platform_settings_table`
5. `recreate_marketplace_users_table`
6. `create_user_role_table`
7. `create_audit_logs_table`

## Seeders

- `RolePermissionSeeder`
- `PlatformSettingsSeeder`
- `FoundationUserSeeder` (super/admin/moderator dev accounts)

## Dev foundation accounts

| Email | Role | Password (local only) |
|-------|------|------------------------|
| super@tamam.local | super_admin | Password123! |
| admin@tamam.local | admin | Password123! |
| mod@tamam.local | moderator | Password123! |

## Verification

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
```

## Out of scope (Phase 1B+)

- Authentication HTTP endpoints
- Refresh token table and cookie flow
- Categories, listings, search, messaging, images, frontend, admin UI
