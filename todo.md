# TODO

## Cloudflare Turnstile for chat endpoint

If the `/api/v1/chat` endpoint receives abuse beyond what the current protections handle (multi-tier rate limiting, browser validation middleware), implement Cloudflare Turnstile (invisible mode) for bot-proof verification.

- Validate token on new conversations only (skip when `conversation_id` is provided)
- Backend: middleware to POST token to Cloudflare's `/siteverify` endpoint
- Frontend: load Turnstile script, attach token to chat requests
- Negligible latency impact (~20-50ms on first message only)
