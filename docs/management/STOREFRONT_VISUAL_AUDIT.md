# EasyTire storefront visual coherence audit

Issue: [#26 — Storefront visual coherence and interaction quality against customer direction](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/26)

Audit date: 2026-08-30

Audit branch: `codex/issue-26-visual-coherence-audit`

Accepted application revision inspected on staging: `ee512d723e78a65a89bfdee4268c3a779f632b33`

Audit base: `3cd51232ac7cdb161c094ab9d31cfcd38713e30c`

Browser: Playwright `1.62.1`, Chromium `151.0.7922.34`

## Executive assessment

The current storefront is materially more coherent, mature and customer-presentable than the earlier rounded and compressed iterations. The approved sharp automotive direction is visible across the principal page families: the hero and finder do not overlap at the four named desktop widths, the site alternates dark and light sections, the local workshop media is intentional, the catalogue no longer feels squeezed, buttons stay anchored on hover, and all 41 Bokmål surfaces fit without document-level horizontal overflow at all six required viewports.

This is not a final visual sign-off. Two P1 conditions prevent that:

1. a core service-request form exposes raw framework keys (`validation.required` and `validation.accepted`) to customers in both Bokmål and the hidden Russian QA route;
2. the real NDI catalogue is still correctly hidden by the stale-feed fail-closed gate, leaving one isolated test tyre, no public wheel inventory and no representative supplier product imagery.

Six bounded P2/P3 improvements remain: mobile live-filter feedback, long service-detail balance, global focus treatment, the universal sharp-geometry override, Blade inline-style drift, and a small set of undersized standalone mobile links. These are suitable for separate remediation Issues after PM accepts this exact audit head.

No storefront/CSS remediation, deployment, NDI write, payment, notification or production mutation is included in this audit.

## Verdict by product dimension

| Dimension | Verdict | Audit conclusion |
|---|---|---|
| Overall visual identity | `MATCH` | Crisp automotive geometry, Barlow/Barlow Condensed hierarchy, restrained red accents and dark/light alternation read as one EasyTire product. |
| Home first screen | `MATCH` | Vehicle, wordmark, finder and seasonal CTA remain legible and non-overlapping at 1920, 1440, 1366 and 1280 widths. |
| Home composition | `MATCH` | Dekkhotell follows the hero; compact process, prices, image-led content, services, reviews and contact/map/social composition follow the accepted direction. |
| Responsive integrity | `MATCH` | No document-level horizontal overflow across 41 Bokmål surfaces at six viewports; mobile order is intentional rather than a squeezed desktop grid. |
| Hover and motion | `MATCH` | Representative controls keep `transform: none`; service-card motion is restricted to a restrained `scale(1.015)` on media, not the card. |
| Keyboard focus | `DESIGN_SYSTEM_DRIFT` | Focus is visible, but the global white 3px outline plus red 5px shadow produces a double treatment and differs from components that suppress or override part of the ring. |
| Catalogue shell | `MATCH` with finding | The grid, drawer and cards are spacious and modern; mobile live-filter progress/count feedback is not visible inside the open drawer. |
| Product content | `CONTENT_DEPENDENCY` | The honest blueprint fallback is acceptable, but the stale external NDI feed prevents representative catalogue/image acceptance. |
| Forms | `INTERACTION_DEFECT` | Layout, disabled calendar fields and phone validation are coherent; service-request required/consent errors leak raw translation keys. |
| Shared pages | `MATCH` | Prices, About, contact/map, legal pages, empty states and 404 share the same visual language. |
| Hidden Russian QA route | `MATCH` with finding | Representative layouts and typography remain stable at desktop/mobile; the same validation-key defect is reproduced. |

## Source reconciliation

Later accepted customer and owner decisions were applied over older drafts. `kfc.nu` and any marketplace template remain references only; no template-copy requirement was inferred.

| Primary reference | Accepted meaning checked in this audit | Result |
|---|---|---|
| Freelancehunt `63792521` | Modern, beautiful, fast and unified service/shop site | Broad match across page families; remaining defects are listed atomically below. |
| Telegram `94711`, `94758` | Typography/style inspiration only | Barlow family and strong display hierarchy are coherent without copying a reference layout. |
| Telegram `94954–94978` | Alternate dark and light sections; avoid an all-dark aggressive site | Match. |
| Telegram `94972`, `94979` | Hero image/vehicle/logo and finder must not overlap | Match at 1920×1080, 1440×900, 1366×768 and 1280×800. |
| Telegram `94982` | One strong seasonal CTA beside the complete shop finder | Match. |
| Telegram `94984–94993` | Remove unexplained strip; use a compact understandable process | Match. |
| Telegram `95001`, `95005` | Image-led promotions and service cards | Match on current published media; product imagery remains an NDI dependency. |
| Telegram `95007`, `95010`, `95012` | Remove marked block; Dekkhotell after hero; reviews direction accepted | Match. |
| Telegram `95014`, `95019` | Contact/map/social composition; translucent sticky header only if readable | Match; the header remains readable before/after scroll. |
| Freelancehunt `63934686`, `63936502`, `63936526` | Sharp first-screen version preferred | Visible match; implementation uses an overly broad override, recorded as design-system drift. |
| Freelancehunt `63857149` | Missing imagery makes the store look unfinished | Confirmed as a current external content dependency, not concealed with fabricated images. |
| Freelancehunt `63934803`, `63935040` | Avoid generated `É/én` and `mobilt dekkskift`; prefer `dekkskift hjemme hos deg` | No forbidden public wording found in the inspected surfaces. The legacy slug remains an internal route alias only. |
| Correction PDF pages 1–8 | Coherent sharp redesign, mobile priority, professional filters/category navigation | Broad match; mobile feedback and service-detail rhythm require bounded refinement. |

The source identities and privacy-safe hashes remain canonical in [`WEBSITE_SOURCE_REGISTER.md`](WEBSITE_SOURCE_REGISTER.md). No raw private archive, customer PII or supplier payload is reproduced here.

## Scope and method

### Browser matrix

- desktop: `1920×1080`, `1440×900`, `1366×768`, `1280×800`;
- touch/mobile: `390×844`;
- touch/narrow: `320×800`.

Each project used a fresh Chromium context, Bokmål locale and one worker. Service workers were blocked for deterministic reads. The active server symlink and `current/REVISION` were read back immediately before documentation and both resolved to `ee512d723e78a65a89bfdee4268c3a779f632b33`.

### Public coverage

The staging pass inspected 41 Bokmål surfaces per viewport:

- home and sticky/mobile navigation states;
- services index, 13 current service routes and 10 customer/SEO canonical landings;
- fitment lookup, seasonal mobile/workshop requests and a generic battery request;
- prices, contact, About and store entry;
- tyre and wheel catalogues, isolated test product, empty cart/checkout;
- privacy, terms and an intentional 404 shell.

The hidden Russian route was checked on 14 representative surfaces at `1440×900` and `390×844`. Catalogue filter-open, loading, empty-results and product-fallback states were exercised at those same representative widths.

Safe local SQLite fixtures covered populated catalogue, product with media, populated cart, checkout default/error, seasonal schedule disabled/manual states, and generic service validation in Bokmål and Russian. Local configuration explicitly disabled NDI submission and payments and used log mail plus a synchronous queue.

### Measurements and human review

The automated pass recorded response status, final URL, title, `lang`, viewport width, document scroll width, body/header/footer height, product/fallback/disabled/invalid counts, element overflow, small interactive targets and console/page/network failures. Representative default/hover/focus/selected/sticky/open-drawer states were sampled from computed CSS and visually compared.

The human review then inspected the full-page captures as compositions, not only as green tests. It judged typography, section rhythm, empty space, media crop, hierarchy, icon language, colour balance and whether mobile surfaces felt intentionally designed.

### Deterministic result summary

- staging full audit: `16 passed`, `8 intentionally skipped` (deep catalogue/Russian checks run only at the two representative widths), one worker;
- focused interaction evidence rerun: `6 passed`, one worker;
- safe local synthetic state audit: `6 passed`, one worker;
- document-level horizontal overflow: `0` across all 246 Bokmål page/viewport records;
- page errors/request failures: `0`;
- console errors: only the expected resource error on the intentional 404 route, once per project;
- unexpected HTTP 5xx: `0`.

Some internally clipped decorative children extend beyond their parent on mobile (the horizontal home price rail and SVG fallback artwork). In every such record `documentElement.scrollWidth` still equals the viewport width; these are not page overflow defects.

## Atomic findings

The canonical machine-readable register is [`STOREFRONT_VISUAL_FINDINGS.csv`](STOREFRONT_VISUAL_FINDINGS.csv).

### AUD-26-001 — P1 `INTERACTION_DEFECT`: raw validation keys reach customers

**Observed.** Submitting the battery service request with required fields empty, using the isolated local environment and no external side effect, renders localized errors for registration/date/problem but raw `validation.required` for name/email/phone and `validation.accepted` for consent. The same defect appears in the hidden Russian route.

**Root cause.** `StoreServiceRequest::messages()` supplies only service-specific overrides. The project has no `lang/nb/validation.php` or `lang/ru/validation.php`, so Laravel cannot resolve its base validation messages. `CheckoutRequest` also relies on base messages for several rules and should be covered during remediation even though this exact visual reproduction is on the service form.

**Evidence.** [`04-service-validation-locales.jpg`](../../evidence/issue-26/04-service-validation-locales.jpg); `application/app/Http/Requests/Storefront/StoreServiceRequest.php:52`, `:62`; `application/resources/views/storefront/requests/create.blade.php:295`.

**Boundary.** Add complete project-supported Bokmål/Russian validation dictionaries or explicit request messages, then add feature and Playwright assertions that no rendered error matches an untranslated `domain.key` pattern.

**Acceptance.** Every required, accepted, email, date, string and custom-phone error shown on public forms is natural Bokmål; hidden Russian parity is natural Russian; no technical key reaches the DOM.

### AUD-26-002 — P1 `CONTENT_DEPENDENCY`: real catalogue and supplier imagery are not visually acceptable yet

**Observed.** Staging correctly exposes only the isolated `NDI TEST` tyre because the supplier feed is stale. The tyre catalogue therefore has one fallback-only fixture and the wheel catalogue has no products. The blueprint fallback is honest and branded, but it cannot substitute for representative real tyres/wheels or prove final grid/media consistency.

**Root cause.** External supplier inbox/feed freshness and official image availability, already tracked by `FND-01`/`EXT-04`; the application is correctly failing closed.

**Evidence.** [`03-catalog-states.jpg`](../../evidence/issue-26/03-catalog-states.jpg); staging `/dekk`, `/felger`, `/produkter/NDI-TEST-4765205551649102`.

**Boundary.** Restore official NDI delivery and a successful import without weakening freshness gates; then repeat image coverage, tyre/wheel mix and product-card visual acceptance. Do not fabricate product photos.

**Acceptance.** Freshness health is green, representative tyre and wheel inventory is public, official media or an approved customer source covers the expected catalogue, and fallback usage is measured and intentional.

### AUD-26-003 — P2 `INTERACTION_DEFECT`: mobile live-filter feedback is hidden behind the open drawer

**Observed.** Changing a select/checkbox or typing in search triggers an immediate live request while the full-height mobile drawer remains open. The only loading label and result count live in the results region behind that drawer. The sticky footer still says “Vis produkter”, so the visible contract reads as explicit apply even though the catalogue is already changing. The filter-open and delayed-loading evidence is visually indistinguishable apart from entered text.

**Root cause.** `catalog-live.js` binds `change`/debounced `input` directly and toggles `[data-catalog-loading]` inside the results region. The mobile filter footer contains reset/apply controls but no mirrored loading/result state.

**Evidence.** Top row of [`03-catalog-states.jpg`](../../evidence/issue-26/03-catalog-states.jpg); `application/resources/js/catalog-live.js:22`, `:67`, `:73`, `:98`, `:124`; `application/resources/views/storefront/catalog/index.blade.php:234`, `:243`.

**Boundary.** Choose one explicit component contract: either true live filtering with a visible busy/result-count state in the sticky drawer footer, or deferred apply that does not issue requests until the button is activated. Preserve abort/history/accessibility behaviour.

**Acceptance.** At 390 and 320 widths the user can always tell that a request is running and how many results will be shown; stale results cannot look current; keyboard and screen-reader busy state remains correct.

### AUD-26-004 — P2 `VISUAL_DEFECT`: long service articles leave an excessive empty second column

**Observed.** Long SEO/service content is placed in the first half of a generic equal-column split while the second half contains only a short sticky CTA. After the CTA, most of the desktop page is visibly blank on the right. The article feels constrained rather than editorial and breaks the otherwise deliberate page rhythm.

**Root cause.** `services/show.blade.php` uses the generic `.et-split`; `pro-design.css` defines it with equal columns and `align-items: center`, while `.et-sticky-cta` is a small sticky panel.

**Evidence.** [`05-service-detail-layout.jpg`](../../evidence/issue-26/05-service-detail-layout.jpg); `application/resources/views/storefront/services/show.blade.php:67`, `:68`, `:94`; `application/resources/css/pro-design.css:52`, `:470`, `:611`.

**Boundary.** Introduce a service-detail-specific editorial layout: wider article plus narrower top-aligned sticky aside, with a measured max text width and intentional behaviour after the aside ends. Do not globally change every `.et-split` consumer.

**Acceptance.** At 1440/1366/1280 the article and CTA feel balanced from the first paragraph through prices/FAQ, without a page-long dead column; mobile order remains article then CTA.

### AUD-26-005 — P2 `DESIGN_SYSTEM_DRIFT`: sharp geometry depends on a universal `!important` override

**Observed.** The visible sharp result matches the customer decision, but it is enforced by a universal selector covering every storefront element and pseudo-element. Exceptions then restore circles and drawer corners with additional `!important` rules. This overrides 86 radius declarations in `pro-design.css` and 26 in `storefront-v3.css`, making future component changes brittle and order-dependent.

**Root cause.** `application/resources/css/storefront-shapes.css:33–36` and its exception block at `:74–96`.

**Evidence.** The current visual match is visible in [`01-home-desktop-matrix.jpg`](../../evidence/issue-26/01-home-desktop-matrix.jpg), [`06-key-public-surfaces.jpg`](../../evidence/issue-26/06-key-public-surfaces.jpg); root cause is source-level.

**Boundary.** Keep the accepted sharp appearance but move geometry to component tokens/contracts. Remove the universal wildcard and retain explicit functional circle exceptions.

**Acceptance.** No universal storefront `border-radius !important`; each reusable surface obtains geometry from a documented token; current visual snapshots remain stable.

### AUD-26-006 — P2 `DESIGN_SYSTEM_DRIFT`: 110 storefront Blade inline styles bypass shared tokens

**Observed.** A deterministic scan finds 110 `style=` attributes under `resources/views/storefront`. They include recurring margins, colour, grid and visibility presentation across catalogue, product, cart, checkout, request and service views. Dynamic values are sometimes legitimate, but repeated static spacing/state values cannot be changed consistently from the design system.

**Root cause.** Incremental Blade composition without extracting repeated presentation into component/modifier classes.

**Evidence.** Representative occurrences: `services/show.blade.php:73`, `:78`, `:82`, `:96`, `:98`, `:104`; `catalog/index.blade.php:251`; `requests/create.blade.php:84`, `:107`, `:181`, `:295`.

**Boundary.** Inventory the 110 occurrences; retain only values that are genuinely data-driven; replace repeated static declarations with semantic component modifiers and token-backed utilities in bounded page-family batches.

**Acceptance.** Static one-off spacing/colour/layout styles are removed from the targeted storefront families, visual snapshots stay stable, and future global rhythm changes have one responsible layer.

### AUD-26-007 — P2 `DESIGN_SYSTEM_DRIFT`: the global focus ring is a double treatment

**Observed.** Keyboard focus is obvious, but the global rule combines a white 3px outline with a red 5px box shadow. On the finder tab this appears as a heavy double rectangular ring, while the primary header button visibly exposes a different subset of the same rule. Default/hover/selected states are otherwise stable and do not move.

**Root cause.** `application/resources/css/app.css:76–80` applies both layers to every storefront focus-visible target; component borders/shadows then interact with that global rule.

**Evidence.** [`08-reusable-interaction-states.jpg`](../../evidence/issue-26/08-reusable-interaction-states.jpg) and computed-state JSON from the 1440 audit (`outline: white solid 3px`; finder `box-shadow: red 0 0 0 5px`).

**Boundary.** Define one tokenized focus-visible contract that works on dark and light surfaces, with intentional high-contrast variants where necessary, and remove component-specific accidental suppression/stacking.

**Acceptance.** Focus is clearly visible in keyboard tests without a mismatched double ring, layout jitter or hover/selected ambiguity on every representative component family.

### AUD-26-008 — P3 `ACCESSIBILITY_CONFLICT`: several standalone mobile text links have small hit areas

**Observed.** At 390/320, standalone editorial links such as the Dekkhotell/mobile CTA are about 21px high and several service “Les mer” actions are 37–43px high. Breadcrumbs are 14–16px high. Inline legal links inside prose are exempt from large-target treatment, and checkbox inputs are wrapped by clickable labels; those were not classified as defects.

**Root cause.** The shared selector at `storefront-v3.css:1042–1052` makes links inline-flex but does not set a minimum height; `.et-v3-service a` adds only top padding. Breadcrumb anchors have no block padding in `pro-design.css:317–319`.

**Evidence.** Recorded target rectangles in `bokmal-surfaces.json` for 390/320; representative mobile composition in [`02-home-mobile-matrix.jpg`](../../evidence/issue-26/02-home-mobile-matrix.jpg).

**Boundary.** Give standalone CTA/editorial/service/breadcrumb links an adequate clickable area without visually inflating inline prose links. Preserve the current compact aesthetic.

**Acceptance.** Standalone mobile actions meet the project’s 44px target convention (or a documented equivalent spacing rule), remain visually aligned, and do not create new wrapping/overflow at 320px.

## Confirmed matches

### Hero, media and first-screen composition

The four desktop crops show stable relationships among the logo, hero headline, seasonal CTA, vehicle/workshop media and finder. There is no overlap at the customer-relevant 1280/1366 widths. On 390/320 the hierarchy becomes a deliberate single column; no action is clipped.

### Home sequence and brand tone

The current home page reflects the accepted source ordering and tone: Dekkhotell immediately after the hero, compact process, price rail, image-led service/promotion content, reviews and local contact. Red remains an action/accent rather than filling every surface. The local workshop photography makes the site feel like a Tromsø business rather than a generic marketplace template.

### Catalogue and commerce shell

The current catalogue filter is spacious, grouped and readable. Product cards, fallback artwork, empty results, populated cart and checkout share the same sharp shapes and typography. The fallback honestly says that the product image is missing; it does not invent a tyre image. Safe synthetic fixtures demonstrate that real media will fit the component contract.

### Responsive and navigation behaviour

All named page/viewport combinations retain their main actions. The mobile menu opens as a clear full-width navigation surface. The sticky header uses a readable `rgba(255,255,255,.9)` background and restrained shadow. No document-level overflow or header/hero overlay was observed.

### Forms and disabled states

Input/select/date/checkbox/radio layouts remain usable at 390/320. The seasonal calendar’s unavailable/manual-time fields look intentionally disabled. Checkout phone validation is localized and sets its invalid state correctly. The separate service required/consent localization defect is recorded as AUD-26-001.

### Map, legal and shared shell

Home/contact embed the map immediately rather than hiding it behind a consent-like placeholder. About, prices, privacy, terms and 404 retain the same header, typography, border system and footer. No map network failure appeared in the audit.

## CSS and component root-cause inventory

Static source scan on the audit base:

| File | Lines | `!important` | `border-radius` | `:hover` | `:focus-visible` | `transform:` | `box-shadow` |
|---|---:|---:|---:|---:|---:|---:|---:|
| `app.css` | 121 | 4 | 0 | 1 | 4 | 0 | 6 |
| `design-tokens.css` | 162 | 0 | 0 | 0 | 0 | 0 | 0 |
| `pro-design.css` | 847 | 4 | 86 | 25 | 1 | 25 | 38 |
| `storefront-v3.css` | 3745 | 0 | 26 | 23 | 7 | 23 | 23 |
| `storefront-shapes.css` | 98 | 4 | 4 | 1 | 0 | 0 | 2 |

The problem is not the raw number of declarations alone. The architectural drift is:

1. design tokens define the intended vocabulary;
2. `pro-design.css` and `storefront-v3.css` each contain overlapping component/state vocabularies;
3. `storefront-shapes.css` forces the accepted appearance across both with a universal high-specificity override;
4. 110 Blade inline styles create a fourth presentation layer;
5. `app.css` applies a global double focus effect that individual components partially expose or suppress.

The safest remediation order is to correct customer-facing defects first, then reduce layer conflict while holding visual snapshots stable.

## Recommended remediation grouping

### Group A — conversion correctness

- AUD-26-001: complete localized validation contract for every public form.
- Add regression assertions for raw translation-key leakage.

### Group B — catalogue and content readiness

- AUD-26-002: restore NDI feed/media externally without weakening fail-closed behaviour.
- AUD-26-003: decide and implement one mobile filter update contract.
- Repeat catalogue visual acceptance only after representative tyres, wheels and images exist.

### Group C — page composition

- AUD-26-004: service-detail-specific editorial/aside layout.
- AUD-26-008: standalone mobile touch-target pass.

### Group D — design-system consolidation

- AUD-26-007: one focus-visible contract.
- AUD-26-005: remove universal sharp wildcard while preserving appearance.
- AUD-26-006: migrate static inline styles page-family by page-family.

Do not combine all four groups into one large visual rewrite. Each group should have its own Issue, exact source expectation, screenshot matrix and rollback boundary.

## Limitations and deliberately unverified states

- Fresh real NDI tyres, wheels, stock mix and official product-image coverage were not available. This is classified as `CONTENT_DEPENDENCY`, not faked.
- No real service request, order, Dintero payment, NDI order, email or notification was submitted. Successful external post-payment/fulfilment visuals remain `NOT_VERIFIED` under the Issue runtime gate.
- No production or staging deployment was performed. The inspected staging revision remains unchanged.
- Raw screenshots and JSON remain untracked under `tmp/issue-26/`; only the compact, redacted contact sheets indexed below are review evidence.

## Evidence

See [`evidence/issue-26/INDEX.md`](../../evidence/issue-26/INDEX.md) for the exact matrix, filenames, generation command and privacy boundary.
