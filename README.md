# Tamam Marketplace

> A modern, scalable marketplace platform built for Qatar and designed for future expansion across the GCC.

---

## About Tamam

Tamam is a next-generation online marketplace where individuals and businesses can buy, sell, rent, advertise, and connect through a fast, secure, and user-friendly platform.

The project focuses on trust, simplicity, performance, and scalability.

Unlike traditional classifieds websites, Tamam is designed from day one to support millions of users while maintaining a premium user experience.

---

## Vision

To become the most trusted and user-friendly marketplace platform in Qatar and later expand across the Gulf region.

---

## Mission

Build a marketplace that makes buying and selling simple, safe, and enjoyable for everyone.

---

## Core Values

- Trust
- Simplicity
- Performance
- Quality
- Scalability
- Security

---

# Technology Stack

## Runtime (Frontend / Admin)

| Tool | Version |
|------|---------|
| **Node.js** | 22 LTS |
| **pnpm** | 9+ |
| **Next.js** | 16 (App Router) |
| **React / React DOM** | 19.2.4 (compatible with Next.js 16) |
| **TypeScript** | 5.9.3 |
| **Package manager** | pnpm 9 (only) |

Use `nvm use` or `fnm use` in the repo root (see `.nvmrc`).

## Frontend

- Next.js 16 (App Router)
- React 19 / React DOM 19
- TypeScript 5+
- pnpm (only package manager; lockfile at repo root)
- Tailwind CSS
- Zustand
- TanStack Query
- React Hook Form
- Zod

---

## Backend

- Laravel 12
- PHP 8.4+
- PostgreSQL
- Redis
- Laravel Queue
- Laravel Scheduler

---

## Storage

- Amazon S3 Compatible Storage

---

## Notifications

- Firebase Cloud Messaging
- Apple Push Notification Service

---

## Payments

- Stripe
- Future Qatar Payment Providers

---

# Project Structure

```
tamam/

docs/
frontend/
backend/
mobile/
admin/
shared/
docker/
scripts/
.github/
README.md
```

---

# Documentation

All project documentation is located inside:

```
docs/
```

### Source of truth

**[docs/PRODUCT_REQUIREMENTS.md](docs/PRODUCT_REQUIREMENTS.md)** is the primary source of truth for business behavior. API and database documents are aligned to it.

### Main documents

| Document | Purpose |
|----------|---------|
| [PRODUCT_REQUIREMENTS.md](docs/PRODUCT_REQUIREMENTS.md) | Business requirements (source of truth) |
| [PROJECT_BLUEPRINT.md](docs/PROJECT_BLUEPRINT.md) | Vision, objectives, market |
| [SYSTEM_ARCHITECTURE.md](docs/SYSTEM_ARCHITECTURE.md) | Technical architecture |
| [DATABASE.md](docs/DATABASE.md) | Data model and relationships |
| [API_SPEC.md](docs/API_SPEC.md) | REST API contract |
| [UI_GUIDELINES.md](docs/UI_GUIDELINES.md) | UI/UX standards |
| [CURSOR_RULES.md](docs/CURSOR_RULES.md) | Engineering rules for development |
| [PHASE_1_TECHNICAL_DESIGN.md](docs/PHASE_1_TECHNICAL_DESIGN.md) | Phase 1 implementation blueprint (Phase 0.5) |

### Architecture Decision Records

Key technical decisions are documented in [docs/adr/](docs/adr/README.md):

- Authentication (JWT + refresh tokens)
- Admin application architecture
- Internationalization
- Real-time messaging
- Search engine strategy
- File storage and media

---

# Implementation Phases

Development is split into reviewable milestones. **Each phase requires approval before the next begins.**

| Phase | Scope | Status |
|-------|-------|--------|
| **0A** | Documentation alignment (API, DATABASE, ADR, README) | Complete |
| **0B** | Repository scaffolding (Docker, backend, frontend, admin, shared) | Complete |
| **0C** | CI/CD, workspace, scripts, configuration | Complete |
| **0D** | Health endpoint, API error envelope, i18n shell, placeholder pages | Complete |
| **0.5** | Phase 1 technical design (implementation blueprint) | Approved |
| **1+** | Business features (auth, listings, etc.) | Not started |

### Approved product decisions

- JWT access tokens + refresh tokens with rotation and session revocation
- Separate Next.js admin app in `/admin` sharing `/shared` contracts
- MVP Core deferrals: saved searches, search history, listing video, automated duplicate detection, moderation appeals, advanced promotion types
- Launch promotion: **Featured Listing** only
- New user listings require manual moderation (configurable auto-publish for trusted users via platform settings)

## Monorepo setup

This repository uses **pnpm workspaces** for `frontend`, `admin`, and `shared`.

```bash
pnpm install
# or
./scripts/setup.ps1
```

### Workspace commands

```bash
pnpm dev:frontend
pnpm dev:admin
pnpm typecheck
pnpm lint
```

Shared contracts are imported as `@tamam/shared`.

---

## Phase 0D — Local development

Phase 0D delivers API error handling, the health endpoint, bilingual frontend routing, placeholder UI shells, and Docker orchestration. No business features or authentication are included yet.

### Local URLs

| Service | URL |
|---------|-----|
| Frontend (Arabic default) | http://localhost:3000/ar |
| Frontend (English) | http://localhost:3000/en |
| Admin | http://localhost:3001 |
| Admin login placeholder | http://localhost:3001/login |
| API health | http://localhost:8000/api/v1/health |
| Mailhog UI | http://localhost:8025 |
| MinIO console | http://localhost:9001 |

Visiting http://localhost:3000 redirects to `/ar` (default locale for the Qatar launch).

### Docker Compose

Start all services (PostgreSQL, Redis, Mailhog, MinIO, Laravel backend, Next.js frontend, Next.js admin):

```bash
docker compose up --build
```

First backend startup runs `composer install` and generates an `APP_KEY` if missing. Node.js **22 LTS** is used inside the frontend and admin containers.

Verify reachability:

```bash
curl http://localhost:8000/api/v1/health
curl http://localhost:3000/ar
curl http://localhost:3001
```

### Health endpoint

`GET /api/v1/health` returns a safe JSON payload using the unified success envelope:

```json
{
  "success": true,
  "message": "Service is healthy.",
  "data": {
    "status": "ok",
    "service": "tamam-api",
    "api_version": "v1"
  }
}
```

No environment variables, credentials, paths, package versions, or secrets are exposed.

### API error envelope

API routes return JSON errors in this shape:

```json
{
  "success": false,
  "message": "Human-readable error message.",
  "errors": {},
  "data": null
}
```

Handled status codes include 404, 405, 422, 429, and 500. Stack traces and sensitive internal details are never returned to clients.

### Frontend internationalisation

- **Arabic (`/ar`)** — default locale, RTL layout
- **English (`/en`)** — LTR layout
- Translation strings live in `frontend/messages/ar.json` and `frontend/messages/en.json`
- A language switcher toggles between locales while preserving the current path
- HTML `lang` and `dir` attributes are set per locale

---

Every contribution must follow:

- Clean Architecture
- SOLID Principles
- Domain Driven Design
- Feature-Based Structure
- API First Design

---

# Coding Standards

- TypeScript for frontend
- PHP Standards (PSR) for backend
- REST API
- Reusable Components
- Reusable Services
- Strong Typing
- Clean Code

---

# Security Standards

- HTTPS Only
- JWT Authentication (access + refresh tokens)
- Input Validation
- Password Hashing
- Rate Limiting
- Secure File Uploads

---

# Performance Goals

Homepage:

< 2 seconds

Search:

< 500 ms

API Response:

< 300 ms

Optimized Images

Green Core Web Vitals

---

# Supported Platforms

- Web
- Mobile Responsive
- Android (Future)
- iOS (Future)

---

# Marketplace Categories

- Cars
- Real Estate
- Jobs
- Services
- Electronics
- Furniture
- Fashion
- Sports
- Pets
- Business
- Community

---

# Development Workflow

Every new feature should follow this process:

1. Read [PROJECT_BLUEPRINT.md](docs/PROJECT_BLUEPRINT.md)
2. Read [PRODUCT_REQUIREMENTS.md](docs/PRODUCT_REQUIREMENTS.md)
3. Read [SYSTEM_ARCHITECTURE.md](docs/SYSTEM_ARCHITECTURE.md)
4. Read relevant [ADR](docs/adr/README.md) documents
5. Read [CURSOR_RULES.md](docs/CURSOR_RULES.md)
6. Implement the feature
7. Test the feature
8. Review the code
9. Commit changes

Never skip documentation before implementation.

---

# Git Branch Strategy

main

Production-ready code.

develop

Integration branch.

feature/*

Individual features.

bugfix/*

Bug fixes.

hotfix/*

Critical production fixes.

---

# Future Roadmap

- Native Mobile Applications
- AI Smart Search
- AI Recommendations
- AI Fraud Detection
- Business Dashboard
- Advertising Platform
- Loyalty Program
- Wallet
- Public API
- Partner API

---

# License

Private Project

All rights reserved.

---

# Final Principle

Tamam is not just another classifieds website.

Every design decision, feature, and line of code must improve at least one of the following:

- User Experience
- Trust
- Performance
- Security
- Scalability

If it does not improve any of them,

it should not be implemented.
