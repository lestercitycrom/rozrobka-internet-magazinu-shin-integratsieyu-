# Issue #10 — redacted audit evidence

Audit date: 2026-08-29

Staging revision: `01a931d432b32a5a0633d1b508256b225387d429`

This is a deliberately small evidence set. It contains no credentials, customer PII, private exports, provider payloads or authenticated admin screenshots.

## Public visual evidence

| Page/state | Desktop | Mobile |
|---|---|---|
| Home | [`staging-home-desktop-1440x900.png`](staging-home-desktop-1440x900.png) | [`staging-home-fold-mobile-390x844.png`](staging-home-fold-mobile-390x844.png), [`staging-home-fold-mobile-320x800.png`](staging-home-fold-mobile-320x800.png) |
| Immediate Google map | [`staging-home-map-desktop-1440x900.png`](staging-home-map-desktop-1440x900.png) | Covered by responsive route assertions; no duplicate screenshot retained. |
| Shop entry | [`staging-store-desktop-1440x900.png`](staging-store-desktop-1440x900.png) | [`staging-store-mobile-320x800.png`](staging-store-mobile-320x800.png) |
| Tyre catalogue | [`staging-tires-desktop-1440x900.png`](staging-tires-desktop-1440x900.png) | [`staging-tires-mobile-320x800.png`](staging-tires-mobile-320x800.png) |
| Services index | [`staging-services-desktop-1440x900.png`](staging-services-desktop-1440x900.png) | [`staging-services-mobile-390x844.png`](staging-services-mobile-390x844.png) |
| Seasonal service | [`staging-seasonal-service-desktop-1440x900.png`](staging-seasonal-service-desktop-1440x900.png) | [`staging-seasonal-service-mobile-390x844.png`](staging-seasonal-service-mobile-390x844.png) |

## Checks represented by the audit record

- Public smoke at 1440×900, 390×844 and 320×800: HTTP 200, H1/title present, no horizontal overflow, page errors or console errors.
- All 11 exact SEO-workbook routes: HTTP 200, non-empty H1/title/meta and exact canonical.
- `/ru`: Russian locale plus `noindex,nofollow`.
- Map: live embed loaded after normal scroll/wait and displayed the customer-confirmed destination.
- Authenticated custom admin: read-only GET smoke across dashboard, catalogue, imports, requests, bookings, orders, storefront settings and promotions at all three viewports; no PII evidence retained.
- Redacted health: database/cache/queue/scheduler/worker green; supplier-feed freshness and disk warning documented as `FND-01` and `FND-03`.

## Automated baseline

- `php artisan test`: 402 passed, 3,066 assertions, 1 skipped.
- repository Playwright suite: 124 passed, 5 intentional skips, 0 failed.
- `npm run build`: passed, 61 modules transformed.
- `php vendor/bin/pint --test`: passed.
- `php scripts/validate-project-control.php`: passed.

The exact source inventory, classification and reproduction notes live in [`../../docs/management/WEBSITE_CONFORMITY_AUDIT.md`](../../docs/management/WEBSITE_CONFORMITY_AUDIT.md) and [`../../docs/management/WEBSITE_REQUIREMENTS_TRACEABILITY.csv`](../../docs/management/WEBSITE_REQUIREMENTS_TRACEABILITY.csv).
