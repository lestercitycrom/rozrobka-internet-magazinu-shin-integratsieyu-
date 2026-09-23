# Issue #81 — admin request workspace evidence

Evidence was captured from an isolated local SQLite runtime with synthetic E2E records. No staging data, customer PII, credentials, provider payloads or server paths are present.

## Interface

- [Desktop workspace, 1440 px](desktop-1440.png)
- [Desktop workspace, 1280 px](desktop-1280.png)
- [Mobile workspace, 390 px](mobile-390.png)
- [Mobile workspace, 320 px](mobile-320.png)

The desktop capture shows the contextual right-hand drawer over the preserved request list. The mobile captures show the same workspace as a full-width viewport panel; the list remains behind it and is not replaced by a phantom result row.

## Verified flows

- Search and storage-operation filters, including literal `%` and `_` input and same-vehicle matching.
- Drawer open/close context, keyboard focus return, Escape and dirty-input protection.
- Owner/Operations quick actions with optimistic version conflicts, UUID idempotency and redacted history.
- Conflict feedback shows the latest server state next to the exact invalid field and keeps the operator's unsaved value for retry.
- Explicit client linking without rewriting the immutable request contact snapshot; matching phone/e-mail records produce a warning but are never merged automatically.
- Overlapping work queues and bounded list queries at 1,001 clients / 500 requests.
- Bokmål and hidden Russian QA text at 1440, 1280, 390 and 320 px.

## Local results

- Laravel/Pest: **676 passed, 1 intentionally skipped, 6,279 assertions**.
- Playwright request workspace: **9 passed** — desktop, 390 px and 320 px.
- Playwright clients/workday regression: **9 passed** — desktop, 390 px and 320 px.
- Accepted-volume search measurement: **9 database queries / 36.26 ms** on the isolated local SQLite fixture with 1,001 clients and 500 requests. This is a local regression metric, not a production performance claim.
- Existing-data SQLite upgrade: passed; an existing request retained its snapshot, received `operation_version = 1`, received no invented event, and a simulated partial migration resumed safely.
- Production Vite build, compiled Blade views, Pint and diff whitespace checks: reported in the PR after the final exact-head run.

The MySQL 8 integration regression contains the equivalent existing-data, version/audit, JSON search and same-vehicle assertions. It requires the isolated MySQL 8 CI service; the local machine only exposes MySQL 5.7, so this document does not claim a local MySQL 8 execution.
