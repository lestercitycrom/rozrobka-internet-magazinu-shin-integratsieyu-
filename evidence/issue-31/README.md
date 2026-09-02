# Issue #31 — services and request composition evidence

## Evidence boundary

- Before source: exact accepted base `d69d8d2a70d492c039ae2e76be67938bda274349` (`before-source.json`).
- After source: the exact PR #38 head containing this directory; the immutable SHA is recorded in the final `ENGINEER UPDATE` and CI run.
- Runtime: local synthetic E2E database only. Error-state captures submit invalid forms, create no service request and trigger no notification or external integration.
- Media boundary: existing customer/project repository assets only. No generated, scraped, stock or rights-uncertain photography was added.

## Deterministic service-media inventory

| Published destination | Presentation | Repository source / honest fallback |
|---|---|---|
| `mobilt-dekkskift` | repository photo, feature crop | `customer-local-mobile-service-photo` |
| `dekkverksted` | repository photo | `customer-local-workshop-photo-alt` |
| `tesla-og-elbil` | repository photo | `customer-local-mobile-service-photo` |
| `bobil-og-varebil` | repository photo | `customer-local-exterior-fleet-photo` |
| `dekkhotell` | repository photo, feature crop | `customer-local-tire-storage-photo` |
| `dekk-felg-og-hjulpakker` | honest branded fallback | approved service-specific media unavailable |
| `omlegging-balansering` | repository photo | `customer-local-workshop-photo` |
| `dekkreparasjon` | repository photo | `customer-local-workshop-photo-alt` |
| `tpms-sensor` | honest branded fallback | approved service-specific media unavailable |
| `oljeskift` | honest branded fallback | approved service-specific media unavailable |
| `bilbatteri` | repository photo | `customer-local-exterior-fleet-photo` |
| `bildiagnose` | repository photo | `customer-local-workshop-photo` |
| `lyspaerer-viskerblad` | honest branded fallback | approved service-specific media unavailable |

The executable record is `service-media-inventory.json`: every required viewport contains the same 13 ordered destinations, 9 repository-media uses and 4 honest fallbacks. Mobile cards measure 184 px high; minimum widths are 358 px at 390 and 296 px at 320.

## Composition measurements

`service-detail-geometry.json` records the long battery page at 1440, 1366, 1280, 390 and 320.

- Desktop intro/aside width ratio: 2.40–2.52, with top edges aligned within 0 px.
- Desktop continuation width: 920 px (at least 71.8% of the service top grid), so prices/FAQ do not remain trapped beside a finished short aside.
- Mobile order is always intro → request aside → continuation → final action.
- At every desktop first fold there is one, not two, primary service-request action.

## Screenshot matrix

All screenshots are full page unless their name contains `first-fold` or `final-action`.

- Services index before/after: `services-index` at `1440x900`, `1280x800`, `390x844`, `320x800`.
- Long service detail before/after: `service-detail` at `1440x900`, `1366x768`, `1280x800`, `390x844`, `320x800`.
- Battery CTA before/after: `battery-first-fold` and `battery-final-action` at all five detail viewports.
- Generic request default/error before/after: Bokmål at `1440x900` and `390x844`; hidden Russian QA at `1440x900` and `320x800`.

Representative pairs:

- `before-services-index-1440x900.jpg` / `after-services-index-1440x900.jpg`
- `before-services-index-320x800.jpg` / `after-services-index-320x800.jpg`
- `before-service-detail-1440x900.jpg` / `after-service-detail-1440x900.jpg`
- `before-service-detail-320x800.jpg` / `after-service-detail-320x800.jpg`
- `before-request-nb-1440x900-default.jpg` / `after-request-nb-1440x900-default.jpg`
- `before-request-ru-320x800-error.jpg` / `after-request-ru-320x800-error.jpg`

## Human review

- The index now reads as one local workshop offer: real EasyTire environments lead, crop choices match service meaning, and missing photography is disclosed by one consistent fallback contract.
- Hover changes border/shadow and applies only a 1.5% image scale; card geometry does not move. Real `Tab` traversal leaves the card itself unframed and gives its title link the single accepted 3 px focus outline.
- The long detail page keeps a readable wide article, a restrained conversion aside and a full-width final action without the old page-long empty column.
- The generic request form gains selected-service context without adding another oversized marketing hero or changing field order.
- Bokmål and hidden Russian QA preserve old input, show the localized error summary and focus the first invalid field.

## Focus correction evidence

`service-focus-contract.json` records computed outline, offset, colour and shadow state for both a featured and a regular service offer at `1440x900` and `390x844`. Each target is reached through real `Tab` traversal; the title link has the one visible focus treatment, while the parent card has neither outline nor shadow and keeps identical dimensions. The four matching viewport captures are named `focus-service-{featured|regular}-{1440x900|390x844}.jpg`.

## Protected behaviour

Automated regression covers unchanged service destinations/canonical aliases, published copy, prices and disclaimers; battery-only registration/date requirements; seasonal/mobile/workshop and Dekkhotell fulfilment; request idempotency; old input; and zero persistent request/outbox records for invalid submissions. The exact commands and counts are recorded in the PR and final `ENGINEER UPDATE`.
