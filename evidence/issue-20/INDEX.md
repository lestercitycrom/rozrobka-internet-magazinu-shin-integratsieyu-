# Issue #20 — battery service request contract evidence

Verification date: 2026-08-30

Runtime boundary: local and CI synthetic verification only. No staging deployment, supplier call, payment call, notification delivery or other external side effect was performed.

## Implemented contract

- `registration_number` and `preferred_date` are required only for the `bilbatteri` request route.
- The desired date must be a valid date that is not earlier than today.
- Bokmål and the hidden Russian QA route provide localized labels, guidance and validation messages.
- The form explicitly explains that the desired date is not a confirmed appointment.
- Invalid requests create no service request, submission receipt or outbox message.
- Valid requests retain the existing idempotency contract and persist the registration number and desired date already supported by the schema.
- Existing administration detail screens display both values; historical rows with null values remain readable.
- Other generic services remain optional for both fields, while the existing mounting date requirement remains unchanged.

## Local verification

| Gate | Result |
|---|---:|
| Battery contract feature suite | 8 passed, 54 assertions |
| Adjacent storefront/admin request suites | 31 passed, 195 assertions |
| Full Laravel/Pest regression | 410 passed, 3120 assertions; 1 Windows-only symlink check skipped |
| Playwright request suite | 21 passed across buyer desktop, buyer mobile and Russian QA preview |
| Production asset build | Passed |
| Laravel Pint | Passed |
| Git diff whitespace check | Passed |

The Playwright battery scenario verifies required controls, accessible descriptions, localized server errors, focus on the first invalid field, no global overflow and a successful synthetic submission on all three selected projects.

## Compatibility notes

No database migration was required because the request model and schema already contained `registration_number` and `preferred_date`. No external integration or staging runtime approval is requested by this evidence.
