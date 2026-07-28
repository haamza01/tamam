# SYSTEM_ARCHITECTURE.md

# Tamam Marketplace System Architecture

Version: 1.1

Status: Approved

Architecture decisions: See [docs/adr/](./adr/README.md).

Business requirements: [PRODUCT_REQUIREMENTS.md](./PRODUCT_REQUIREMENTS.md)

---

# Overview

Tamam is designed as a modern, scalable, secure, and maintainable marketplace platform.

The architecture must support:

- Millions of users
- Millions of listings
- High availability
- Horizontal scaling
- Mobile applications
- Web platform
- Admin dashboard (separate Next.js app — see [ADR-002](./adr/002-admin-application-architecture.md))
- Future AI services

---

# Architecture Principles

The system must always follow:

- Clean Architecture
- Domain Driven Design (DDD)
- SOLID Principles
- Separation of Concerns
- Dependency Injection
- Repository Pattern
- API First Design

---

# High Level Architecture

```

Users

↓

Frontend (Next.js)          Admin (Next.js)

↓                           ↓

        REST API (/api/v1)

↓

Backend (Laravel)

↓

Database (PostgreSQL)

↓

Object Storage (S3-compatible)

↓

External Services

```

Shared TypeScript contracts live in `/shared` and are consumed by both frontend and admin.

---

# Technology Stack

## Frontend

Framework

Next.js 16 (App Router)

Runtime

Node.js 22 LTS

Package Manager

pnpm

Language

TypeScript 5+

UI

React 19 (compatible with Next.js 16)

Styling

Tailwind CSS

Icons

Lucide Icons

Forms

React Hook Form

Validation

Zod

State Management

Zustand

Data Fetching

TanStack Query

Internationalization

next-intl (see [ADR-003](./adr/003-internationalization.md))

---

## Backend

Framework

Laravel 12

Language

PHP 8.4+

Authentication

JWT access tokens + refresh tokens (see [ADR-001](./adr/001-authentication-jwt-refresh.md))

Queue

Laravel Queue

Scheduler

Laravel Scheduler

Caching

Redis

Storage

Laravel Filesystem (see [ADR-006](./adr/006-file-storage-and-media.md))

---

## Database

PostgreSQL

Primary database

See [DATABASE.md](./DATABASE.md)

---

## Cache

Redis

Used for:

Caching

Rate Limiting

Queue

Search Cache

---

## Search Engine

Phase 1

PostgreSQL Full Text Search (see [ADR-005](./adr/005-search-engine-strategy.md))

Phase 2

Meilisearch

Future

ElasticSearch

---

## File Storage

Amazon S3 Compatible

Examples

AWS S3

Cloudflare R2

DigitalOcean Spaces

Development: MinIO via Docker

---

## Image Processing

Automatic Resize

Automatic Compression

WebP

Thumbnail Generation

Listing video processing deferred post-MVP Core.

---

## Email

SMTP

Future

Mailgun

Resend

Amazon SES

MVP notification channels: in-app + email.

---

## Push Notifications

Firebase Cloud Messaging

Apple Push Notification Service

Deferred beyond MVP Core for notifications module.

---

## Payments

Stripe

Future

Qatar Payment Providers

Apple Pay

Google Pay

Launch promotion type: Featured Listing only.

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

```

---

# Frontend Structure

```

frontend/

src/

app/

components/

features/

hooks/

lib/

services/

types/

styles/

utils/

```

---

# Backend Structure

```

backend/

app/

Domain/

Application/

Infrastructure/

Presentation/

Http/

Console/

Jobs/

Events/

Policies/

Observers/

```

---

# API Layer

REST API

Versioned

/api/v1

Future

/api/v2

See [API_SPEC.md](./API_SPEC.md)

---

# Authentication Flow

User Login

↓

Validate Credentials

↓

Generate Access Token + Refresh Token

↓

Return User Profile

↓

Authenticated Requests (Bearer access token)

↓

Token Refresh (rotates refresh token)

↓

Revoke all sessions on critical account changes

---

# Authorization

RBAC

Roles

Guest

User

Verified User

Business

Moderator

Support

Admin

Super Admin

---

# Security

HTTPS Only

JWT Authentication (access + refresh)

Password Hashing

CSRF Protection

XSS Protection

SQL Injection Protection

Rate Limiting

Request Validation

File Validation

Virus Scan (Future)

Audit Logging

---

# File Upload Flow

Client

↓

Validation

↓

Temporary Storage

↓

Image Processing

↓

Compression

↓

WebP Conversion

↓

Cloud Storage

↓

Database Record

---

# Listing Creation Flow

User

↓

Authentication

↓

Validation

↓

Category Validation

↓

Attribute Validation

↓

Image Upload

↓

Database Transaction

↓

Moderation (manual for new users; configurable auto-publish for trusted users)

↓

Notification

↓

Listing Published

---

# Search Flow

Search Request

↓

Filters

↓

Sorting

↓

Pagination

↓

Cache

↓

Database (PostgreSQL FTS)

↓

Results

---

# Favorites Flow (Phase 1H)

Authenticated user

↓

Listing eligibility check (public visibility + not own listing)

↓

Database transaction (favourite row + atomic `favorites_count` update)

↓

Response

List favourites reuses `PublicListingQueryBuilder` visibility rules and `ListingCardResource`. See [PHASE_1H.md](./PHASE_1H.md).

---

# Messaging Flow

Buyer

↓

Conversation

↓

Message

↓

Database

↓

Notification

↓

Seller

MVP transport: polling (see [ADR-004](./adr/004-real-time-messaging.md))

---

# Notification System

Email

In-App Notifications

SMS (Future)

Push Notifications (Future)

---

# Background Jobs

Email

Image Processing

Thumbnail Generation

Notifications

Analytics

Reports

Cleanup Tasks

---

# Logging

Application Logs

Error Logs

Authentication Logs

Payment Logs

Admin Logs

Audit Logs

---

# Monitoring

Laravel Pulse

Laravel Telescope

Sentry

Uptime Monitoring

Performance Monitoring

---

# Environment Configuration

Development

Testing

Staging

Production

Each environment must have independent configuration.

---

# Deployment

Docker

NGINX

PHP-FPM

Redis

PostgreSQL

Object Storage

SSL

CI/CD

GitHub Actions

---

# Backup Strategy

Daily Database Backup

Hourly Incremental Backup

File Backup

30-Day Retention

Encrypted Backups

---

# Performance Targets

Homepage

< 2 seconds

Search

< 500 ms

API Response

< 300 ms

Image Loading

Optimized

Core Web Vitals

Green

---

# Scalability

Stateless Backend

Horizontal Scaling

Redis Cache

CDN

Load Balancer

Queue Workers

Read Replicas (Future)

Microservices (Future)

---

# AI Architecture

AI must be isolated.

Create dedicated AI services.

Never mix AI logic with business logic.

Possible AI modules:

Smart Search

Smart Recommendations

Listing Quality Analysis

Fraud Detection

Auto Description Generation

Automatic Translation

---

# Coding Standards

TypeScript on Frontend

PHP Standards (PSR)

REST API

SOLID

Clean Code

Meaningful Naming

Reusable Components

Reusable Services

No Duplicate Logic

---

# Testing Strategy

Unit Tests

Integration Tests

Feature Tests

End-to-End Tests

Performance Tests

Security Tests

---

# Future Expansion

Native Mobile Apps

Public API

Partner API

Marketplace Analytics

Business Dashboard

Advertising Platform

Loyalty System

Referral System

Wallet

Coupons

AI Assistant

---

# Non-Negotiable Rules

Never bypass architecture.

Never duplicate business logic.

Never hardcode secrets.

Never write temporary production code.

Never ignore validation.

Never expose sensitive data.

Always prioritize security.

Always prioritize scalability.

Always prioritize maintainability.

Every new feature must fit within this architecture.

If not,

the architecture must be reviewed before implementation.
