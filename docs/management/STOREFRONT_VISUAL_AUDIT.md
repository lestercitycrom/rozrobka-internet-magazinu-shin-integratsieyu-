# EasyTire storefront visual coherence audit — corrected review

Issue: [#26 — Storefront visual coherence and interaction quality against customer direction](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/26)

PR: [#27](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/27)

Audit date: 2026-08-31

Audit branch: `codex/issue-26-visual-coherence-audit`

Application revision inspected on staging: `ee512d723e78a65a89bfdee4268c3a779f632b33`

Audit base: `3cd51232ac7cdb161c094ab9d31cfcd38713e30c`

Browser: Playwright `1.62.1`, Chromium `151.0.7922.34`

Runtime authorization: **NONE**. This audit performed no form submission, order, payment, NDI write, email, notification, production mutation or deployment.

## Corrected executive assessment

The first audit was too optimistic. It correctly recognized that the current sharp version is materially better than the old rounded/compressed version, but it promoted several broad page families to `MATCH` without inspecting the states and page-level composition deeply enough.

The corrected result is:

- **2 P1 conditions**: raw validation keys in a public request form, and the external NDI feed/media dependency that prevents representative live catalogue acceptance;
- **11 P2 findings**: mobile filter feedback, desktop service-detail balance, design-token bypasses, global focus treatment, sticky-header readability, services-index imagery, desktop filter scrolling, empty-cart composition, product buy-box squeezing and over-broad hover shadows;
- **4 P3 refinements**: touch target sizing, generic form composition, catalogue category-rail hierarchy and mobile footer density;
- **22 atomic `MATCH` results** that are narrow enough to defend with evidence;
- **1 deliberately unverified area**: real order/payment/NDI fulfilment success states.

The site is not visually rejected as a whole. The home page, About page, local photography, first-fold geometry and mobile catalogue ordering provide a credible design baseline. The corrected audit rejects the previous claim that the whole catalogue, sticky header, all shared pages and interaction system were already coherent.

No visual remediation belongs in this PR. The purpose of this head is to give the PM an evidence-backed, issue-ready defect inventory.

## What changed after `CHANGES_REQUESTED`

| Previous broad conclusion | Corrected conclusion |
|---|---|
| Sticky header was readable and stable | Geometry is stable, but the 0.9 white background has no blur and underlying headings visibly bleed through on light and dark sections (`AUD-26-009`). |
| Catalogue shell was broadly a match | Mobile ordering is a match, but the live-filter contract is ambiguous (`AUD-26-003`), the desktop panel nests its own scrollbar at 1440/1366/1280 (`AUD-26-011`), and the category rail is visually dashboard-like (`AUD-26-016`). |
| Public visual identity was broadly unified | Home/About are strong, but services index uses 13 repeated synthetic graphics and zero service images (`AUD-26-010`), while generic request forms remain template-like (`AUD-26-015`). |
| Buttons/cards hover was a match | No element changes width/height, but 48 visible elements gain shadows and a later wildcard gives even text-link buttons a raised hover effect (`AUD-26-014`). |
| Shared pages were collectively a match | About, prices, contact, legal and 404 are now assessed separately. About is a strong match; empty cart and product detail contain independent P2 defects. |
| Map was present on first render and therefore accepted | The initial capture only shows Google's loader. A dedicated 8-second network-settled test proves the real map loads on desktop/mobile with HTTP 200 responses and zero failures (`AUD-26-033`). |

## Customer-direction reconciliation

Later accepted customer decisions take precedence over older drafts. `kfc.nu` and the supplied marketplace/template links remain inspiration only; no layout or asset was copied.

| Source direction | Meaning applied here | Corrected result |
|---|---|---|
| Freelancehunt `63792521` | Modern, beautiful, fast, unified service/shop site | Partial match. Strong home/About baseline; named catalogue, form and interaction defects remain. |
| Telegram `94711`, `94758` | Typography/style inspiration only | Barlow/Barlow Condensed and the strong display hierarchy are coherent without copying a layout. |
| Telegram `94954–94978` | Alternate dark/light; avoid an all-dark aggressive site | Match on home and principal marketing pages. |
| Telegram `94972`, `94979` | Hero image, logo, vehicle and finder must not overlap | Measured match at all six viewports (`AUD-26-018`). |
| Telegram `94982` | One seasonal CTA beside the complete shop finder | Match. |
| Telegram `94984–94993` | Remove unexplained strip; compact understandable process | Match (`AUD-26-021`). |
| Telegram `95001`, `95005` | Image-led promotions and service cards | Home promotions match; services index does not (`AUD-26-010`); product media remains external (`AUD-26-002`). |
| Telegram `95007`, `95010`, `95012` | Remove marked block; Dekkhotell after hero; reviews direction | Match (`AUD-26-019`, `AUD-26-022`). |
| Telegram `95014`, `95019` | Contact/map/social; translucent sticky header only if readable | Contact/map match after load; sticky header fails readability (`AUD-26-009`). |
| Freelancehunt `63934686`, `63936502`, `63936526` | Sharp first-screen version preferred | Visible direction matches; implementation still relies on a universal `!important` wildcard (`AUD-26-005`). |
| Freelancehunt `63857149` | Missing imagery makes the store look unfinished | Confirmed as `AUD-26-002`, not hidden with fabricated product photography. |
| Freelancehunt `63934803`, `63935040` | Avoid generated `É/én` and `mobilt dekkskift`; use `dekkskift hjemme hos deg` | No forbidden public wording was found on audited surfaces. The legacy slug is internal only. |
| Correction PDF pages 1–8 | Coherent sharp redesign, mobile priority, professional catalogue | Partial match. Mobile ordering works; catalogue interaction and desktop filter composition remain findings. |

Source identities and privacy-safe hashes remain canonical in [`WEBSITE_SOURCE_REGISTER.md`](WEBSITE_SOURCE_REGISTER.md). This report does not reproduce private archives, credentials, customer PII or supplier payloads.

## Scope and method

### Viewports

- desktop: `1920×1080`, `1440×900`, `1366×768`, `1280×800`;
- primary touch/mobile: `390×844` using the iPhone 13 Chromium profile;
- narrow touch stress test: `320×800`.

### Public surface coverage

The existing deterministic public pass covers **41 Bokmål surfaces at all six viewports** (246 page/viewport records):

- home, sticky/mobile navigation and home section families;
- services index, 13 service routes and 10 SEO/customer landing aliases;
- fitment lookup and seasonal/generic request forms;
- store entry, tyre/wheel catalogue, isolated integration-test product;
- empty cart/checkout;
- prices, About, contact, privacy, terms and intentional 404.

The corrected pass adds:

- **78 composition records**: 13 representative page families × 6 viewports;
- sticky header before scroll and after independent light/dark scroll targets at all six viewports;
- hero/finder/logo/vehicle rectangle intersections at all six viewports;
- **101 visible hover changes** across 11 routes;
- **143 real keyboard Tab stops** across six principal routes;
- explicit desktop submenu hover/focus-within/child-focus states;
- explicit mobile menu group closed/open/child-focus states at 390 and 320;
- real delayed contact-map frame/network state at 1440 and 390;
- safe local populated catalogue/product/cart/checkout, disabled fields and validation errors at 1440 and 390.

### Evidence standard

Compact contact sheets are navigation aids. Selected P1/P2 and disputed `MATCH` screenshots are additionally committed at their native pixel dimensions under [`evidence/issue-26`](../../evidence/issue-26/INDEX.md). Raw matrices and browser logs remain ignored under `tmp/issue-26/`.

## Finding index

The machine-readable canonical ledger is [`STOREFRONT_VISUAL_FINDINGS.csv`](STOREFRONT_VISUAL_FINDINGS.csv). The rows below summarize the actionable findings.

| ID | Severity | Surface | Corrected conclusion |
|---|---|---|---|
| `AUD-26-001` | P1 | Generic request validation | Raw framework translation keys reach customers in NB and RU. |
| `AUD-26-002` | P1 external | Live catalogue/media | Only isolated test inventory is visible; representative NDI catalogue acceptance remains blocked externally. |
| `AUD-26-003` | P2 | Mobile filters | Live requests run behind a drawer that still implies an Apply workflow. |
| `AUD-26-004` | P2 | Service detail desktop | Half-width long article leaves a page-long empty right column below the short CTA. |
| `AUD-26-005` | P2 | Sharp geometry architecture | Universal `*`/pseudo-element `border-radius !important` bypasses component contracts. |
| `AUD-26-006` | P2 | Blade presentation | 110 inline presentation attributes create a fourth design-system layer. |
| `AUD-26-007` | P2 | Keyboard focus | Global white outline + red shadow creates oversized double rings. |
| `AUD-26-008` | P3 | Mobile links | Several standalone actions are below the project's 44px convention. |
| `AUD-26-009` | P2 | Sticky header | Underlying headings/media bleed through the 0.9 background because blur is disabled. |
| `AUD-26-010` | P2 | Services index | 13 repeated synthetic number/icon graphics, zero service images. |
| `AUD-26-011` | P2 | Desktop catalogue filter | Nested internal scrollbar at 1440, 1366 and 1280. |
| `AUD-26-012` | P2 | Empty cart | Duplicate recovery CTA plus oversized dashed empty-state canvas. |
| `AUD-26-013` | P2 | Product buy box | Long fixed-quantity note is squeezed into a 104px column beside competing CTAs. |
| `AUD-26-014` | P2 | Hover states | Universal raised shadow crosses button variants and overrides flat text-link intent. |
| `AUD-26-015` | P3 | Generic request form | Long bordered form shell has weak service context and reads template-like. |
| `AUD-26-016` | P3 | Catalogue category rail | Dense equal tiles and small status labels resemble an admin toolbar. |
| `AUD-26-017` | P3 | Mobile footer | Repeated contact/navigation content dominates short catalogue/cart pages. |

## Detailed P1/P2 findings

### `AUD-26-001` — public service form exposes raw validation keys

**Evidence.** Both Bokmål and hidden Russian safe local submissions render `validation.required` and `validation.accepted`. Full-resolution screenshots: [`28-validation-bokmaal-1440-full.jpg`](../../evidence/issue-26/28-validation-bokmaal-1440-full.jpg) and [`29-validation-russian-1440-full.jpg`](../../evidence/issue-26/29-validation-russian-1440-full.jpg).

**Boundary.** Complete validation dictionaries or explicit request messages for every public request class. Do not change form business rules while correcting copy.

**Acceptance.** No technical translation key reaches the DOM in NB/RU; localized regression tests cover required, email, phone, date and consent cases.

### `AUD-26-002` — representative live NDI catalogue/media is externally blocked

The freshness gate correctly fails closed. The current public state contains one isolated integration-test tyre, no public wheels and no representative official product photos. The fallback is honest; it is not a substitute for catalogue acceptance.

**Boundary.** Restore the authoritative external feed/media path without weakening the freshness gate. Repeat visual acceptance only when representative tyres, wheels and official media exist.

### `AUD-26-003` — mobile filter contract is internally inconsistent

The mobile drawer remains open while changes immediately trigger live network requests. Its footer still presents an explicit `Vis produkter`/Apply action, while loading and updated count are visually behind the overlay.

**Boundary.** Implement either true live feedback inside the drawer or true deferred Apply. Preserve request cancellation, history/query updates and ARIA status.

### `AUD-26-004` — service detail has a desktop page-long blank column

At all four desktop widths the long FAQ/article remains constrained to one half of the layout. The conversion card stops near the first third of the page, leaving the remaining right half empty through prices and FAQs. Mobile is explicitly a separate `MATCH` (`AUD-26-029`).

**Evidence.** [`13-service-detail-1440-full.jpg`](../../evidence/issue-26/13-service-detail-1440-full.jpg).

### `AUD-26-005` — sharp geometry is implemented through a universal wildcard

`storefront-shapes.css` applies `border-radius: var(--et-radius-sharp) !important` to every descendant and both pseudo-elements, then reintroduces `!important` exceptions for functional circles and the map pin. This produces the requested sharp look but prevents components from owning their geometry.

**Boundary.** Move the accepted 2px shape into component tokens/contracts. Preserve visual snapshots while removing the wildcard and exception cascade.

### `AUD-26-006` — 110 inline style attributes bypass global visual control

Deterministic count by family:

| Family | Inline `style=` count |
|---|---:|
| checkout | 25 |
| requests | 24 |
| fitment | 18 |
| cart | 15 |
| catalogue | 11 |
| services | 9 |
| content | 6 |
| pricing | 2 |

Some dynamic values are legitimate. Repeated static margins, colours, flex/grid and state visibility are not. Migrate by page family, not in one high-risk rewrite.

### `AUD-26-007` — focus is accessible but visually excessive

Real keyboard traversal found all 143 sampled Tab stops focus-visible. The defect is appearance, not discoverability: 138 sampled stops combine a white 3px outline, 3px offset and red 5px shadow. The result becomes a thick double rectangle around large summary rows and submenu items.

**Evidence.** [`22-submenu-keyboard-focus-1440x900.jpg`](../../evidence/issue-26/22-submenu-keyboard-focus-1440x900.jpg), [`23-mobile-menu-group-focus-390x844.jpg`](../../evidence/issue-26/23-mobile-menu-group-focus-390x844.jpg).

### `AUD-26-009` — sticky header content bleed

Before scroll the header background is `rgba(255,255,255,.96)`. After scroll it becomes `.9`; `backdrop-filter` is explicitly disabled. Large page headings remain visible through the logo/navigation/CTA area on light and dark sections and on mobile.

**Evidence.** [`09-sticky-header-light-1440x900.jpg`](../../evidence/issue-26/09-sticky-header-light-1440x900.jpg), [`10-sticky-header-dark-1440x900.jpg`](../../evidence/issue-26/10-sticky-header-dark-1440x900.jpg), [`11-sticky-header-light-mobile-390x844.jpg`](../../evidence/issue-26/11-sticky-header-light-mobile-390x844.jpg).

### `AUD-26-010` — services index is not image-led

Automated composition count: `serviceRows=13`, `serviceMediaImages=0`, `serviceSyntheticGraphics=13`. The alternating burgundy/black/warm numbered tiles make the page feel generated and repetitive, especially in the 8581px mobile document.

**Boundary.** Use the approved local workshop photo pool through a small reusable media contract. Do not fabricate service/product imagery.

### `AUD-26-011` — desktop filter nests a scrollbar at common laptop heights

| Viewport | Filter client height | Filter scroll height | Result |
|---|---:|---:|---|
| 1920×1080 | 831 | 831 | no scroll needed |
| 1440×900 | 766 | 831 | nested scroll |
| 1366×768 | 634 | 831 | nested scroll |
| 1280×800 | 666 | 831 | nested scroll |

The filter also has sticky head/footer, which strengthens the admin-panel impression. Rebalance desktop density/height without changing the mobile drawer contract.

### `AUD-26-012` — empty cart duplicates its recovery action

The DOM contains two identical `Fortsett å handle|/dekk` actions. The inner message sits in an always-360px, 1280px-wide dashed box on 1440 desktop. The large low-information state conflicts with the requested compact, mature tone.

**Evidence.** [`19-cart-empty-1440-full.jpg`](../../evidence/issue-26/19-cart-empty-1440-full.jpg).

### `AUD-26-013` — product quantity explanation is squeezed

The grid is `104px 1fr`. The first cell contains the quantity input plus a long integration-test explanation, so normal prose wraps into a narrow vertical ribbon even on desktop. At 320px the two actions compete beside it. The fixed quantity of 2 is a required sandbox rule and must remain; only composition changes.

**Evidence.** [`17-product-detail-1440-full.jpg`](../../evidence/issue-26/17-product-detail-1440-full.jpg), [`18-product-detail-320-full.jpg`](../../evidence/issue-26/18-product-detail-320-full.jpg).

### `AUD-26-014` — hover elevation ignores component semantics

Across 11 representative routes:

- 101 visible elements change at least one visual property;
- 48 change `box-shadow`;
- 0 change measured width or height;
- representative transforms remain `none`.

The zero-bounce part is accepted as `AUD-26-023`. The defect is that the late sharp selector gives `.et-btn`, product buy and product-more variants the same hover shadow, including `.et-btn--link`, overriding the earlier explicit `box-shadow:none` link contract.

**Boundary.** Preserve stable geometry. Define hover elevation per semantic variant; keep text links flat.

## Atomic page-family ledger

| Family/state | Verdict | Notes |
|---|---|---|
| Home first fold | `MATCH` | No finder/logo/vehicle overlap at six viewports. |
| Home Dekkhotell/media | `MATCH` | Strong local photography and readable overlays. |
| Home process/price rail | `MATCH` | Compact and understandable. |
| Home services/reviews | `MATCH` | Coherent; service index is assessed separately. |
| Sticky header | `P2` | Geometry stable, visual transparency fails readability. |
| Desktop Services submenu | `MATCH` + focus finding | Opens by hover and real keyboard focus-within; next Tab enters submenu. |
| Mobile menu | `MATCH` + focus finding | Full-width, grouped services open, child keyboard reachable. |
| Services index | `P2` | Synthetic visual repetition, zero service images. |
| Service detail desktop | `P2` | Page-long blank second column. |
| Service detail mobile | `MATCH` | Article, prices and CTA stack logically. |
| Shop entry | `MATCH` | Finder, choices, process and local context form a clear hierarchy. |
| Catalogue mobile closed state | `MATCH` | Deliberate order, no horizontal squeezing. |
| Catalogue mobile filter interaction | `P2` | Live-vs-Apply feedback conflict. |
| Catalogue desktop filter | `P2` | Nested scrollbar on three common laptop viewports. |
| Catalogue category rail | `P3` | Dense dashboard-like treatment. |
| Product fallback | `MATCH` | Honest missing-image state; does not fabricate supplier media. |
| Product buy box | `P2` | Fixed-quantity note squeezed by layout. |
| Generic request form | `P3` + P1 validation | Usable but visually generic; error localization fails. |
| Empty cart | `P2` | Duplicate action and oversized empty state. |
| Populated local cart/checkout | `MATCH` | Safe fixtures remain coherent; no external effects. |
| Prices | `MATCH` | Tables compare clearly and stack on mobile. |
| About | `MATCH` | Strongest shared-page reference. |
| Contact information | `MATCH` | Required business data and actions are present. |
| Contact map | `MATCH after settle` | Eager load, no click; real map proven after 8 seconds. |
| Privacy/terms | `MATCH` | Readable measure and hierarchy. |
| 404 | `MATCH` | Branded recovery with one action. |
| Mobile footer | `P3` | Too dominant on short pages. |
| Hidden Russian layout | `MATCH` except validation | QA-only route stays within layout; no public switch inferred. |

## Interaction matrix

### Pointer states

The corrected matrix covers primary, outline, ghost and text-link buttons; header cart; desktop navigation/submenu; service rows; product card/link; category rail; filter summary; input/select/date/textarea/checkbox; footer/social links; mobile menu; mobile details group; mobile filter action and checkbox.

No representative component moves or changes width/height on hover. This directly addresses the owner's prior complaint about bouncing hover effects. The remaining interaction problem is style semantics (`AUD-26-014`), not layout motion.

### Keyboard states

Real `Tab` traversal was used; no `.focus()` shortcut was used for acceptance. The desktop Services parent is reached on Tab 5, its submenu opens through `:focus-within`, and the next Tab reaches the first child. Mobile details behave equivalently once opened.

Focus visibility is functionally reliable but visually overdrawn (`AUD-26-007`).

### Disabled and error states

Safe local fixtures cover:

- enabled seasonal time-slot select;
- disabled manual time-window field;
- generic request validation errors in NB/RU;
- checkout phone error with `aria-invalid=true`;
- populated catalogue/product/cart/checkout.

Control geometry remains stable. Error localization is separated as the P1 defect.

## Confirmed responsive integrity

All 246 existing Bokmål page/viewport records remain within viewport width plus one pixel. This does not mean every page is visually good; it means there is no document-level horizontal overflow, clipped primary action or accidental desktop grid squeezed beyond the viewport.

At 390 and 320 the catalogue category rail, filter trigger, result count, sort and product card stack deliberately. At 320 the product-detail buy box remains the primary responsive defect.

## Map verification correction

The old audit used iframe presence as a proxy for visual readiness. The corrected audit distinguishes three states:

1. iframe exists immediately;
2. an early screenshot can show a Google loader/blank map canvas;
3. after an 8-second network settle the real map is rendered.

At both 1440 and 390:

- the iframe redirects to `/maps/embed`;
- map scripts and tiles return HTTP 200;
- no map request failure is recorded;
- the exact EasyTire location card and pin are visible;
- no consent-style click is required.

The correct verdict is a qualified `MATCH`, not an immediate zero-latency claim and not a site defect.

## Design-system root causes

The current appearance is composed from four overlapping layers:

1. `design-tokens.css` defines the intended vocabulary;
2. `pro-design.css` provides legacy/base storefront contracts;
3. `storefront-v3.css` adds a second component/state vocabulary;
4. `storefront-shapes.css` forces the final sharp appearance with wildcard specificity;
5. 110 Blade inline styles bypass all four for page-local presentation;
6. `app.css` adds a global two-layer focus effect.

This explains why the site can look coherent in static screenshots while hover/focus/geometry rules remain difficult to change consistently.

## Recommended PM issue sequence

The PM should create bounded issues only after accepting this audit head.

1. **Conversion correctness**
   - `AUD-26-001` validation localization.

2. **Visible interaction defects**
   - `AUD-26-009` sticky readability;
   - `AUD-26-007` single focus contract;
   - `AUD-26-014` per-variant hover contract;
   - `AUD-26-003` mobile live-filter feedback.

3. **Catalogue/product composition**
   - `AUD-26-011` desktop filter scroll;
   - `AUD-26-013` product buy box;
   - `AUD-26-012` empty cart;
   - `AUD-26-016` category hierarchy.

4. **Service/form composition**
   - `AUD-26-004` service detail desktop;
   - `AUD-26-010` image-led services index;
   - `AUD-26-015` generic request shell;
   - `AUD-26-008` mobile touch targets;
   - `AUD-26-017` mobile footer density.

5. **Design-system consolidation after visual behaviour is locked**
   - `AUD-26-005` remove universal geometry wildcard;
   - `AUD-26-006` migrate inline presentation by page family.

6. **External catalogue readiness**
   - `AUD-26-002` only when authoritative NDI feed/media is available.

## Runtime boundary and unverified states

`AUD-26-040` remains `NOT_VERIFIED_BY_DESIGN`. This PR does not claim visual acceptance for a real successful Dintero payment, real NDI order, real service request, real email or notification. Those transitions require a separately authorized sandbox/runtime issue with isolated references and redacted evidence.

## Verification results

The corrected audit head passed every authorized gate on 31 August 2026:

| Gate | Result |
|---|---|
| read-only staging visual audit | 42 passed, 36 intentionally viewport-specific skips, 0 failed |
| safe local synthetic visual states | 6 passed, 0 failed |
| full buyer/admin Playwright suite | 127 passed, 5 project-defined skips, 0 failed |
| Laravel application suite | 410 passed, 1 skipped, 3,120 assertions |
| frontend production build | passed |
| Pint formatting check | passed |
| project-control validation | passed |
| evidence build, CSV/path validation and `git diff --check` | passed |

The full buyer/admin run used one worker with payments, NDI order submission and NDI sandbox order submission disabled, log mail and isolated local state. No external write was authorized or performed.

## Final audit verdict

The sharp design direction is the right baseline and the storefront has several genuinely strong surfaces. It is not yet ready for final visual sign-off. The corrected audit provides enough atomic evidence for the PM to accept/reject individual findings and then create sequential remediation issues without asking the owner to choose technical organization.

This audit PR contains tests, documentation and evidence only. It changes no CSS, Blade, JavaScript, content, runtime configuration or deployed release.
