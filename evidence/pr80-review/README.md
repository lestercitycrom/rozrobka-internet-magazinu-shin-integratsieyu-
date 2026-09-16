# PR #80 — R1–R4 follow-up

Scope: the owner's `EasyTire_PR80_Review_Fixes.zip`, reviewed head `06d3715658716859cb98c723a2de7529cc433477`. Continue Issue #79 / PR #80, preserving the functional PR #78 base `1326895e93b5f6bea67426100c282172be869564`. This iteration does not deploy, merge, change integrations, or mutate staging data.

## Implementation

- R1: the shared featured-service card places the approved photograph above an opaque charcoal caption in normal flow. No photo overlay, darkening, stronger type, changed copy or ordering. Both the current collection partial and the legacy home fallback use this treatment.
- R2: the price view selects `pricing`, so NB/RU desktop and mobile navigation identify only Priser / Цены as current.
- R3: the shared narrow-screen footer stacks contact groups; the email keeps its original font and intact `mailto`, without ellipsis or global no-wrap rules.
- R4: record the final application commit locally, with normal and reduced-motion interactive demonstrations. Preserve the old intermediate recording separately; it is not final-version evidence.

## Regression and evidence

`application/tests/E2E/design-finish.spec.ts` now has seven journeys across six NB/RU × 1440/390/320 projects (42 tests), not 42 distinct user scenarios. They cover the accepted product/cart/checkout presentation, seasonal field grouping and focus, empty/404 recovery, services grid and reduced motion, plus R1–R3. The card tests compare computed foreground/background contrast (at least 4.5:1), containment, normal/hover/focus and extended localized copy. Related geometry is measured in one frame to avoid smooth-scroll sampling races.

`CustomerNavigationRevisionTest` additionally asserts exactly one current destination in both rendered menus and both languages. Existing full CI remains the proportional functional regression; no duplicated new full-suite config.

The final handoff archive contains the filled `ACCEPTANCE_RESULTS.md`, exact application SHA/environment manifest, NB/RU captures, human-paced normal/reduced-motion videos and their interaction log. Capture only after the application commit is fixed; any subsequent application edit invalidates that binding. Local fixture evidence is not staging evidence. Source-approved photography may be shown alongside hermetic seeded content; there are no live external submissions.

The earlier 44 captures were route × viewport samples covering 18 routes, not 44 independent interaction scenarios. This follow-up adds explicit interactions rather than relabelling those samples.

## Local checks

From `application`, using the available PHP 8.3 runtime:

```text
npm run build
php artisan test --filter=CustomerNavigationRevisionTest
npx playwright test --config=playwright.design-finish.config.ts
```

The browser config rejects `FILTERS_LIVE_URL` and uses its isolated snapshot fixture. The separate human-paced capture fixture likewise disables supplier submissions, payments, communications and integration deliveries. No final booking/order submission is used for these recordings. Filled results and final CI status are reported against the exact commit, not asserted in advance here.

Executor verification is not independent approval. Return the exact candidate to the existing reviewer; do not resolve their review, merge or deploy on its behalf.
