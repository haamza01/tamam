# ADR-004: Real-Time Messaging Strategy

**Status:** Accepted  
**Date:** 2026-07-27  
**Deciders:** Project Owner, Lead Architect

## Context

Tamam requires in-app messaging between buyers and sellers ([PRODUCT_REQUIREMENTS.md](../PRODUCT_REQUIREMENTS.md) §15). Users expect near-real-time delivery of messages and unread counts. Push notifications are deferred for MVP (in-app + email only).

## Decision

### MVP Core: polling + queued notifications

1. **Message storage** in PostgreSQL (`conversations`, `messages` tables)
2. **Client polling** for new messages (recommended interval: 5–10 seconds when conversation is open; 30–60 seconds on inbox)
3. **Server-side events** dispatch notification jobs via Laravel Queue when messages are created
4. **Email notifications** sent based on user preferences (not for every message if user is active — future optimization)

### Future: WebSockets / SSE

- Add Laravel Reverb, Pusher, or SSE endpoint when traffic justifies real-time transport
- Messaging domain logic must remain independent of transport layer
- Frontend switches from polling to WebSocket subscription without API contract changes

### Safety features (MVP)

- User blocks (`user_blocks` table)
- Conversation-level block and archive
- Report conversation via unified `/reports` endpoint
- Moderators access messages only with permission

## Consequences

### Positive

- Simpler infrastructure for MVP Core
- No WebSocket server to operate at launch
- Domain logic decoupled from transport

### Negative

- Polling increases API load at scale
- Not truly real-time (acceptable for MVP per PRD near-real-time wording)

### Mitigations

- Cache unread counts in Redis
- Use ETag or `since_id` parameter on message fetch to reduce payload
- Plan WebSocket migration path in Phase 13+

## Related documents

- [API_SPEC.md](../API_SPEC.md) — Messages endpoints
- [DATABASE.md](../DATABASE.md) — conversations, messages, user_blocks
