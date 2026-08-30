# Issue #26 visual evidence index

Issue: [#26 — Storefront visual coherence and interaction quality against customer direction](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/26)

## Capture identity

- staging URL: `https://staging-easytire.bethub.fun`;
- active staging release read-back: `/var/www/staging-easytire.bethub.fun/releases/20260830061020-ee512d723e78`;
- exact `current/REVISION`: `ee512d723e78a65a89bfdee4268c3a779f632b33`;
- capture date: 2026-08-30;
- Playwright: `1.62.1`;
- Chromium: `151.0.7922.34`;
- locale: Norwegian Bokmål (`nb-NO`), plus hidden Russian parity where named;
- mobile context: touch enabled; primary mobile uses the Playwright iPhone 13 profile with an explicit `390×844` viewport;
- workers: `1`; no parallel browser load.

## Viewport matrix

| Project | Viewport | Purpose |
|---|---:|---|
| `desktop-1920x1080` | 1920×1080 | Wide hero/finder/logo composition and section scale |
| `desktop-1440x900` | 1440×900 | Primary desktop acceptance and deep state capture |
| `desktop-1366x768` | 1366×768 | Common compact laptop |
| `desktop-1280x800` | 1280×800 | Customer-relevant smaller laptop/Mac geometry |
| `mobile-touch-390x844` | 390×844 | Primary touch/mobile acceptance and deep state capture |
| `mobile-narrow-320x800` | 320×800 | Narrow mobile stress check |

## Compact review evidence

| File | What it proves | Principal finding/result |
|---|---|---|
| [`01-home-desktop-matrix.jpg`](01-home-desktop-matrix.jpg) | First fold at all four desktop widths | Hero/finder/logo do not overlap; accepted sharp direction is stable |
| [`02-home-mobile-matrix.jpg`](02-home-mobile-matrix.jpg) | First fold at 390 and 320 | Deliberate mobile hierarchy, usable navigation/actions, no squeezed desktop grid |
| [`03-catalog-states.jpg`](03-catalog-states.jpg) | Mobile filter open/loading, empty results, NDI test fallback | AUD-26-002 and AUD-26-003; honest fallback and external content limitation |
| [`04-service-validation-locales.jpg`](04-service-validation-locales.jpg) | Same service-form failure in Bokmål and hidden Russian | AUD-26-001 raw `validation.*` keys |
| [`05-service-detail-layout.jpg`](05-service-detail-layout.jpg) | Long desktop service article and short sticky CTA | AUD-26-004 page-long empty second column |
| [`06-key-public-surfaces.jpg`](06-key-public-surfaces.jpg) | About, contact/map, prices and store entry first folds | Shared visual language and direct map composition |
| [`07-local-commerce-states.jpg`](07-local-commerce-states.jpg) | Safe synthetic populated catalogue/product/cart/checkout | Commerce component/state coherence without external side effects |
| [`08-reusable-interaction-states.jpg`](08-reusable-interaction-states.jpg) | Primary button default/hover/focus and finder default/selected/focus | Stable hover dimensions; AUD-26-007 double focus treatment |

## Raw capture boundary

Full-resolution screenshots, metrics and browser problem logs remain untracked under `tmp/issue-26/`. They include no successful real submission and no customer record. Only the compact review sheets above are committed.

The evidence contains public staging content and isolated synthetic local fixtures. It contains no credentials, access tokens, customer PII, private NDI payload, real order, real payment, email or notification.

## Reproduction

From `application/`:

```powershell
.\node_modules\.bin\playwright.cmd test --config=playwright.issue-26.config.ts
.\node_modules\.bin\playwright.cmd test --config=playwright.issue-26.local.config.ts
```

From the repository root, after both raw runs:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\build-issue-26-evidence.ps1
```

The staging suite is read-only. The local suite uses fresh SQLite fixtures with NDI submission and payments explicitly disabled, log mail and a synchronous queue.
