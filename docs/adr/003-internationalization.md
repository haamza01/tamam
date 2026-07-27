# ADR-003: Internationalization (Arabic / English)

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Tamam launches in Qatar with **Arabic and English** support from MVP. Requirements include RTL layout, translated categories, localized dates/numbers, and QAR as default currency.

## Decision

### Frontend

- Use **next-intl** with the **Next.js 16 App Router**
- Supported locales: `ar` (default for Qatar market), `en`
- `ar` uses **RTL** layout; `en` uses **LTR**
- Locale switcher persisted in user profile when authenticated, otherwise cookie/localStorage
- Typography per [UI_GUIDELINES.md](../UI_GUIDELINES.md): Inter (English), IBM Plex Sans Arabic or Noto Sans Arabic (Arabic)

### Backend

- API accepts `Accept-Language` header or `locale` query parameter where relevant
- Category and attribute labels stored in translation tables (see [DATABASE.md](../DATABASE.md)):
  - `category_translations`
  - `category_attribute_translations`
  - `category_attribute_option_translations`
- User-generated content (listing titles, descriptions) stored in the language entered by the user; search indexes both Arabic and English content

### Shared

- Locale constants (`ar`, `en`) defined in `/shared`
- API error messages localized where practical

### Currency and numbers

- Default currency: **QAR**
- Number and date formatting uses locale-aware formatting on frontend
- Database stores amounts as decimal/numeric; formatting is presentation-layer

## Consequences

### Positive

- Meets Qatar market requirements from launch
- Translation tables allow GCC expansion without schema changes
- RTL-first design improves Arabic UX

### Negative

- All UI strings require translation maintenance
- Category admin must manage two locales minimum
- Search across Arabic and English adds indexing complexity

## Related documents

- [PRODUCT_REQUIREMENTS.md](../PRODUCT_REQUIREMENTS.md) — Sections 7.2, 7.3
- [DATABASE.md](../DATABASE.md) — Translation tables
