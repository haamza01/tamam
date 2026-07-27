# @tamam/shared

Shared TypeScript contracts for Tamam apps.

Contains:

- API response types
- Pagination types
- Zod schemas for API envelopes
- Locale and promotion constants

No business logic belongs in this package.

## Setup

```bash
cd shared
npm install
npm run typecheck
```

Workspace linking is configured via pnpm workspaces and `@tamam/shared` imports.
