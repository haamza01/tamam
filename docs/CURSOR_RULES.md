# CURSOR_RULES.md

# Tamam Marketplace - Cursor Development Rules

Version: 1.0

---

# Purpose

This document defines the mandatory engineering standards for the Tamam Marketplace project.

Cursor Agent must follow these rules for every task without exception.

If any instruction from a task conflicts with these rules, these rules take precedence unless explicitly overridden by the project owner.

---

# General Principles

Always think before writing code.

Do not generate code immediately.

Understand the feature first.

Identify dependencies.

Identify possible side effects.

Then implement.

---

# Development Philosophy

The goal is NOT to finish quickly.

The goal is to build a platform that can scale to millions of users.

Quality is always more important than speed.

Readable code is more important than clever code.

Maintainability is more important than shortcuts.

---

# Project Architecture

Always follow:

- Clean Architecture
- SOLID Principles
- Feature-Based Structure
- Domain Driven Design (DDD)
- Repository Pattern
- Dependency Injection
- Separation of Concerns

Never violate architecture for convenience.

---

# Code Quality

Write production-ready code only.

Never write demo code.

Never write placeholder implementations.

Never leave TODO comments unless requested.

Avoid duplicated code.

Keep functions small.

Keep classes focused.

Prefer composition over inheritance.

Always use meaningful names.

---

# TypeScript

Use TypeScript everywhere.

Never use "any".

Prefer strict typing.

Use interfaces where appropriate.

Use enums only when necessary.

Prefer type aliases for simple structures.

---

# Error Handling

Never ignore errors.

Always return meaningful error messages.

Handle edge cases.

Validate all inputs.

Never trust client-side validation.

---

# Security

Security comes first.

Always validate input.

Sanitize user-generated content.

Prevent SQL Injection.

Prevent XSS.

Prevent CSRF.

Protect sensitive endpoints.

Hash passwords securely.

Never expose secrets.

Never hardcode API keys.

Never hardcode credentials.

---

# Database

Normalize data.

Create indexes when necessary.

Use foreign keys.

Use transactions where required.

Avoid unnecessary queries.

Optimize database performance.

---

# API Standards

Follow REST API conventions.

Use proper HTTP status codes.

Return consistent JSON responses.

Version APIs.

Never expose internal implementation.

---

# Performance

Optimize every feature.

Lazy load where appropriate.

Optimize images.

Minimize API requests.

Avoid unnecessary re-renders.

Cache when appropriate.

Think about scalability before implementation.

---

# Frontend

Mobile First.

Responsive Design.

Accessible UI.

Simple navigation.

Consistent spacing.

Consistent typography.

Fast loading.

Professional animations only.

No unnecessary visual effects.

---

# UI Design

Design philosophy:

Simple.

Modern.

Premium.

Minimal.

Inspired by Apple, Airbnb, Stripe, and Linear.

Avoid visual clutter.

Every component must have a purpose.

---

# Components

Create reusable components.

Never duplicate UI.

Keep components independent.

Follow atomic design where possible.

---

# Forms

Always validate.

Show helpful messages.

Prevent accidental submission.

Provide loading states.

Provide success states.

Provide error states.

---

# Authentication

Secure authentication.

JWT access tokens with refresh tokens (see [ADR-001](./adr/001-authentication-jwt-refresh.md)).

Role-based permissions.

Support future MFA implementation.

---

# Notifications

Provide useful notifications.

Avoid spam.

Never interrupt the user unnecessarily.

---

# Logging

Log important events.

Never log passwords.

Never log secrets.

Log enough information for debugging.

---

# AI Features

Build every AI feature as a separate service.

Never mix AI logic with business logic.

Keep AI optional.

Application must continue working if AI services fail.

---

# Testing

Write testable code.

Support unit testing.

Support integration testing.

Support end-to-end testing.

Avoid tightly coupled code.

---

# Documentation

Document public functions.

Document complex logic.

Keep documentation updated.

---

# Git

Use meaningful commit messages.

Keep commits focused.

Never commit broken code.

---

# Refactoring

If existing code can be improved without changing behavior:

Refactor it.

Improve readability.

Reduce complexity.

Do not over-engineer.

---

# Forbidden Practices

Never use:

- Spaghetti code
- God classes
- Massive functions
- Magic numbers
- Hardcoded strings
- Duplicate logic
- Global mutable state
- Console debugging in production
- Commented-out code
- Unused imports
- Dead code

---

# Marketplace Priorities

Every new feature should improve at least one of these:

- User Experience
- Trust
- Speed
- Security
- Business Value
- Scalability

If it improves none of them,

do not implement it.

---

# Final Rule

Before generating any code, always ask yourself:

Is this production-ready?

Is this scalable?

Is this secure?

Is this maintainable?

If the answer is "No",

rewrite the solution.
