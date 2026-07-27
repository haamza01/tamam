# Tamam Admin

Internal administration dashboard.

## Stack

- Next.js 16 (App Router)
- React 19 / React DOM 19
- TypeScript 5+
- Node.js 22 LTS
- pnpm (monorepo workspace)

## Setup

From the repository root:

```bash
pnpm install
cp admin/.env.example admin/.env.local
pnpm dev:admin
```

Default URL: http://localhost:3001

Shared contracts are available via `@tamam/shared`.
