# Issue #26 corrected visual evidence index

Issue: [#26 — Storefront visual coherence and interaction quality against customer direction](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/26)

PR: [#27](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/27)

## Capture identity

- staging: `https://staging-easytire.bethub.fun`;
- active release read-back: `/var/www/staging-easytire.bethub.fun/releases/20260830061020-ee512d723e78`;
- exact staging `REVISION`: `ee512d723e78a65a89bfdee4268c3a779f632b33`;
- audit date: 2026-08-31;
- Playwright: `1.62.1`;
- Chromium: `151.0.7922.34`;
- public locale: Norwegian Bokmål (`nb-NO`), plus hidden Russian parity where named;
- workers: 1; service workers blocked;
- runtime authorization: **NONE**.

No real request, order, payment, NDI write, email, notification, production mutation or deployment was performed.

## Viewport matrix

| Project | Viewport | Purpose |
|---|---:|---|
| `desktop-1920x1080` | 1920×1080 | wide composition and section scale |
| `desktop-1440x900` | 1440×900 | primary desktop, deep interaction states |
| `desktop-1366x768` | 1366×768 | common compact laptop |
| `desktop-1280x800` | 1280×800 | customer-relevant smaller laptop |
| `mobile-touch-390x844` | 390×844 | primary touch/mobile and deep states |
| `mobile-narrow-320x800` | 320×800 | narrow touch stress test |

## Compact navigation sheets

These sheets help reviewers compare states quickly. They are not substitutes for the native-resolution evidence below.

| File | Scope |
|---|---|
| [`01-home-desktop-matrix.jpg`](01-home-desktop-matrix.jpg) | first fold at four desktop widths |
| [`02-home-mobile-matrix.jpg`](02-home-mobile-matrix.jpg) | first fold at 390 and 320 |
| [`03-catalog-states.jpg`](03-catalog-states.jpg) | mobile filter open/loading/empty and product fallback |
| [`04-service-validation-locales.jpg`](04-service-validation-locales.jpg) | NB/RU raw validation-key failure |
| [`05-service-detail-layout.jpg`](05-service-detail-layout.jpg) | desktop article/CTA imbalance |
| [`06-key-public-surfaces.jpg`](06-key-public-surfaces.jpg) | About, contact, prices and shop entry |
| [`07-local-commerce-states.jpg`](07-local-commerce-states.jpg) | safe populated product/cart/checkout fixtures |
| [`08-reusable-interaction-states.jpg`](08-reusable-interaction-states.jpg) | representative default/hover/focus/selected states |

## Native-resolution P1/P2 evidence

| File | Finding/state proven |
|---|---|
| [`09-sticky-header-light-1440x900.jpg`](09-sticky-header-light-1440x900.jpg) | `AUD-26-009`: light-section text bleeds through sticky header |
| [`10-sticky-header-dark-1440x900.jpg`](10-sticky-header-dark-1440x900.jpg) | `AUD-26-009`: dark-section heading competes with header content |
| [`11-sticky-header-light-mobile-390x844.jpg`](11-sticky-header-light-mobile-390x844.jpg) | `AUD-26-009`: mobile sticky-header bleed |
| [`12-services-index-1440-full.jpg`](12-services-index-1440-full.jpg) | `AUD-26-010`: 13 repeated synthetic graphics, no service media |
| [`13-service-detail-1440-full.jpg`](13-service-detail-1440-full.jpg) | `AUD-26-004`: page-long empty desktop column |
| [`14-catalog-1440-full.jpg`](14-catalog-1440-full.jpg) | `AUD-26-002/011/016`: isolated product, desktop nested filter, category rail |
| [`15-catalog-filter-open-mobile-full.jpg`](15-catalog-filter-open-mobile-full.jpg) | `AUD-26-003`: open filter drawer |
| [`16-catalog-loading-mobile-full.jpg`](16-catalog-loading-mobile-full.jpg) | `AUD-26-003`: live loading occurs behind drawer contract |
| [`17-product-detail-1440-full.jpg`](17-product-detail-1440-full.jpg) | `AUD-26-002/013`: missing media and squeezed quantity explanation |
| [`18-product-detail-320-full.jpg`](18-product-detail-320-full.jpg) | `AUD-26-013`: narrow-screen buy-box competition |
| [`19-cart-empty-1440-full.jpg`](19-cart-empty-1440-full.jpg) | `AUD-26-012`: duplicate CTA and oversized dashed state |
| [`20-request-form-1440-full.jpg`](20-request-form-1440-full.jpg) | `AUD-26-015`: generic form-shell composition |
| [`21-button-outline-hover-1440x900.jpg`](21-button-outline-hover-1440x900.jpg) | `AUD-26-014`: raised hover treatment on outline variant |
| [`22-button-link-hover-1440x900.jpg`](22-button-link-hover-1440x900.jpg) | `AUD-26-014`: text-link button receives the late universal shadow |
| [`22-submenu-keyboard-focus-1440x900.jpg`](22-submenu-keyboard-focus-1440x900.jpg) | `AUD-26-007`: double focus ring in desktop submenu |
| [`23-mobile-menu-group-focus-390x844.jpg`](23-mobile-menu-group-focus-390x844.jpg) | `AUD-26-007`: oversized focus frame on mobile group summary |
| [`28-validation-bokmaal-1440-full.jpg`](28-validation-bokmaal-1440-full.jpg) | `AUD-26-001`: raw validation keys in Bokmål |
| [`29-validation-russian-1440-full.jpg`](29-validation-russian-1440-full.jpg) | `AUD-26-001`: same failure on hidden Russian QA route |

`AUD-26-005` and `AUD-26-006` are source-architecture findings. Their proof is the deterministic CSS/Blade scan recorded in [`STOREFRONT_VISUAL_AUDIT.md`](../../docs/management/STOREFRONT_VISUAL_AUDIT.md), not a screenshot.

## Native-resolution disputed `MATCH` evidence

| File | Atomic result proven |
|---|---|
| [`24-contact-map-loaded-1440x900.jpg`](24-contact-map-loaded-1440x900.jpg) | real Google map after 8-second settle, not merely iframe presence |
| [`25-home-1440-full.jpg`](25-home-1440-full.jpg) | accepted home sequence, photography and first-fold direction |
| [`26-about-1440-full.jpg`](26-about-1440-full.jpg) | strong local/image-led shared-page reference |
| [`27-catalog-mobile-390-full.jpg`](27-catalog-mobile-390-full.jpg) | deliberate mobile catalogue ordering without horizontal squeezing |
| [`30-cart-populated-mobile-full.jpg`](30-cart-populated-mobile-full.jpg) | safe populated cart composition |
| [`31-checkout-validation-mobile-full.jpg`](31-checkout-validation-mobile-full.jpg) | localized checkout error and mobile checkout shell |

Desktop submenu and mobile-menu screenshots simultaneously prove working pointer/keyboard disclosure and the separate focus-appearance defect.

## Raw evidence boundary

Ignored raw data under `tmp/issue-26/` includes:

- full public page screenshots and layout metrics;
- `page-composition.json` for 13 page families at six viewports;
- `sticky-header-states.json` and light/dark viewport captures;
- `hero-geometry.json` at six viewports;
- `hover-inventory.json` across 11 routes;
- `keyboard-focus-order.json` from real Tab traversal;
- `interaction-matrix.json`, desktop submenu and mobile group matrices;
- contact-map frame/request evidence at desktop/mobile;
- safe local validation/disabled/populated commerce fixtures.

Only selected privacy-safe review images are committed. Evidence contains no credentials, access tokens, customer PII, private NDI payload, real payment, real order, email or notification.

## Reproduction

From `application/`:

```powershell
node .\node_modules\@playwright\test\cli.js test --config=playwright.issue-26.config.ts
node .\node_modules\@playwright\test\cli.js test --config=playwright.issue-26.local.config.ts
```

From the repository root after both raw runs:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\build-issue-26-evidence.ps1
```

The staging suite is read-only. The local suite uses fresh SQLite fixtures with NDI submission and payments disabled, log mail and a synchronous queue.

## Accepted run totals

| Gate | Result |
|---|---|
| staging visual suite | 42 passed, 36 viewport-specific skips, 0 failed |
| local synthetic visual suite | 6 passed, 0 failed |
| complete buyer/admin E2E | 127 passed, 5 project-defined skips, 0 failed |

The application regression suite also passed with 410 tests, one skip and 3,120 assertions. The frontend production build, Pint check, project-control validator and evidence/path checks passed. No payment, NDI order, email or notification was sent.
