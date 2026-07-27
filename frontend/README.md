# Tamam Frontend

Public marketplace web application.

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
cp frontend/.env.example frontend/.env.local
pnpm dev:frontend
```

Default URL: http://localhost:3000

Shared contracts are available via `@tamam/shared`.
