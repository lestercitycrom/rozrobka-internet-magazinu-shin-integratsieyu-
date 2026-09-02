# Issue #32 evidence — catalogue, product and cart journeys

## Scope and safety

- Exact accepted base: `9d49ac82ced177d5310a909ddb7e82fff9c3678a` (Issue #31 / PR #38 merge).
- Source data: local/CI synthetic fixtures only. The labelled NDI sandbox product is created by the existing guarded `NdiSandboxCatalogManager`; supplier inquiry/order switches remain disabled.
- Runtime effects: none. No staging/production deployment, NDI request, Dintero request, email, Notion, Telegram, customer submission or notification occurred.
- Migration: none.

## Before evidence

The accepted audit baseline is retained in `evidence/issue-26/`:

- `14-catalog-1440-full.jpg` — nested desktop filter scrollbar and dashboard-like category rail.
- `15-catalog-filter-open-mobile-full.jpg` / `16-catalog-loading-mobile-full.jpg` — ambiguous mobile live-vs-Apply behaviour.
- `17-product-detail-1440-full.jpg` / `18-product-detail-320-full.jpg` — squeezed quantity/note/action composition.
- `19-cart-empty-1440-full.jpg` — duplicate recovery action and oversized dashed empty state.
- `28-catalog-mobile-390-full.jpg` — accepted mobile catalogue order and honest missing-media baseline.
- `31-cart-populated-mobile-full.jpg` / `32-checkout-validation-mobile-full.jpg` — populated synthetic commerce baseline.

## After evidence

### Catalogue and desktop filters

- `catalog-1920x1080.png`
- `catalog-1440x900.png`
- `catalog-1366x768.png`
- `catalog-1280x800.png`
- `catalog-390x844.png`
- `catalog-320x800.png`

`geometry-and-state.json` proves that the complete desktop filter is naturally sized (`clientHeight === scrollHeight`, `overflowY: visible`) at 1920, 1440, 1366 and 1280. All controls remain in normal document scrolling; there is no nested filter scrollbar.

### Category states

- `catalog-category-hover-1440x900.png`
- `catalog-category-focus-1440x900.png`

The category control is a light shop-navigation rail rather than four boxed dashboard tiles. Destinations, current-page semantics and the shared Issue #29 hover/focus contract remain intact.

### Mobile deferred Apply

- `catalog-filter-draft-390x844.png`
- `catalog-filter-draft-320x800.png`
- `catalog-filter-busy-390x844.png`
- `catalog-filter-error-390x844.png`

The E2E contract proves draft-only edits with no catalogue request or history mutation; one bounded Apply request; duplicate-submit prevention; visible busy state; atomic URL/history/results/count success; drawer close/focus handoff; failure with the drawer and draft preserved; successful retry; deterministic Reset and back/forward restoration of applied state only.

### Product detail and buy box

- Media: `product-media-{1440x900,1280x800,390x844,320x800}.png`
- Honest fallback: `product-fallback-{1440x900,1280x800,390x844,320x800}.png`
- Guarded sandbox quantity: `product-sandbox-buy-box-{1440x900,1280x800,390x844,320x800}.png`

Quantity, actions and the sandbox-only explanation now occupy separate readable regions. The approved fixed quantity of two, pricing, stock, fitment and add-on rules are unchanged. `geometry-and-state.json` records their non-overlapping dimensions.

### Cart and checkout

- Empty cart: `cart-empty-{1920x1080,1440x900,1366x768,1280x800,390x844,320x800}.png`
- Populated cart: `cart-populated-1440x900.png`, `cart-populated-390x844.png`
- Checkout regression: `checkout-populated-1440x900.png`, `checkout-populated-390x844.png`

The empty cart has one recovery action, no dashed placeholder and a compact composition. Populated cart and checkout remain backed by the synthetic fixture and perform no checkout submission or external side effect.

## Automated verification

- `tests/E2E/catalog-live.spec.ts` — desktop live filtering plus mobile draft/Apply/busy/error/retry/history/Reset contracts.
- `tests/E2E/catalog-mobile-layout.spec.ts` — natural desktop filter geometry, shop-navigation states, 390/320 usability and no overflow.
- `tests/E2E/product-detail.spec.ts` — media/fallback matrix and sandbox buy-box measurements at 1440/1280/390/320.
- `tests/E2E/cart-composition.spec.ts` — one compact empty-cart recovery action at all six required viewports.
- `tests/E2E/cart-mobile.spec.ts` and checkout/buyer suites — populated commerce regression.
- `tests/Audit/issue-32-evidence.spec.ts` with `playwright.issue-32.config.ts` — deterministic screenshot/state generation.

Final exact-head full-suite counts and GitHub Actions URL are recorded in the PR and final `ENGINEER UPDATE` after the immutable review SHA exists.
