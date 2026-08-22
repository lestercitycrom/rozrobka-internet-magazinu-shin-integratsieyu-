# Admin order operations security

## Filter allowlist

Order list filters for lifecycle status, payment status, and NDI status must accept only values defined by their respective enums. Free-form text is limited to an explicitly bounded reference/email search. UI labels are translated; raw enum values are not presented as operator-facing status labels.

## Snapshot display

Order-item product and pricing snapshots are immutable audit evidence, not generic JSON viewers. Admin rendering must use an explicit per-snapshot key allowlist. Unknown keys, credentials, tokens, request/response bodies, customer-address fields, and arbitrary nested values are omitted. The source payload remains unavailable from the page.

## Operational boundary

Generic status controls may execute only local lifecycle transitions through `TransitionOrder`. External mutations are isolated state-specific commands, never free-form status edits. The order page may reconcile Dintero, void an uncaptured authorization before a supplier order exists, and capture only after payment authorization, accepted NDI order and an explicit goods-received/ready confirmation with operator/time/note audit. It must not expose arbitrary NDI submission, blind C1 retry, manual "mark paid", arbitrary amount capture, credentials, raw provider payloads or supplier cancellation.

## Outbox ownership

`ProcessOutboxMessage` claims a message using `lockForUpdate`. A non-stale `locked_at` guard must run before type, destination-enabled, retry, or other routing paths so an active owner cannot be requeued or modified by a concurrent worker. Delivered messages are idempotently skipped; delivery errors are normalized codes only.

## Acceptance tests

- Invalid enum filter values are rejected; allowed values return only matching orders.
- Detail snapshots show allowed fields and omit unknown/sensitive keys.
- Generic admin transitions use `TransitionOrder`; external actions are rendered only for their exact payment/NDI state and enforce the same server-side guards.
- Capture is impossible without `authorized + NDI accepted + goods ready`; successful capture publishes paid outbox once.
- NDI rejection permits void, while an uncertain supplier result blocks both blind retry and capture.
- A fresh locked outbox message is skipped without changing attempts, availability, status, or error code.
- Delivered, disabled, unknown-type, and destination-exception outbox paths remain idempotent and redacted.
