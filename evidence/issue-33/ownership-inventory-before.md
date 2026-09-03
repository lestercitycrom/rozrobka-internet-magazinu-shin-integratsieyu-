# Issue #33 pre-implementation ownership inventory

Inventory source: accepted `main` presentation at `87509a644022a4df232c118d9bf4bab32f2c3683` (the only branch change before this inventory was the project-control pointer commit).

Deterministic command:

```powershell
rg -n --glob '*.blade.php' 'style\s*=' application/resources/views/storefront
```

## Inline presentation inventory

Total storefront occurrences: **98**. Every value below is static presentation. No value is computed from customer, catalogue, payment or other runtime data. Conditional `hidden`/Blade branches remain behavioural attributes; only the fixed CSS declarations move to semantic classes.

| Family / file | Count | Static occurrences (`line: declaration`) | Data-driven |
|---|---:|---|---:|
| cart / `cart/index.blade.php` | 12 | `42: margin-top:1rem`; `65: width:88px;min-height:40px`; `67: align-self:end`; `69: align-self:end`; `79: margin-top:.6rem`; `91: margin-top:.8rem`; `96: width:88px`; `115: margin-top:.6rem`; `126: margin-top:.45rem`; `147: margin-top:.8rem`; `165: margin-top:1rem`; `167: margin-top:1rem` | 0 |
| catalogue / `catalog/index.blade.php` | 3 | `258: margin:0`; `296: margin-top:1rem`; `309: margin-top:2rem` | 0 |
| catalogue / `catalog/show.blade.php` | 7 | `32: color:var(--et-muted)`; `40: margin-top:1rem`; `98: margin-top:1.2rem`; `105: margin:1.5rem 0 .8rem`; `133: margin-top:1rem`; `136: margin-top:1rem`; `139: margin:0` | 0 |
| checkout / `checkout/create.blade.php` | 12 | `43: margin-bottom:1.2rem`; `46: margin-bottom:1.2rem`; `48: margin-bottom:1.2rem`; `82: margin-top:1.5rem;border-top:1px solid var(--et-line);padding-top:1.3rem`; `122: margin-top:1.5rem`; `124: margin-top:1.2rem`; `125: margin-top:.75rem`; `131: margin-top:1rem`; `142: margin-top:1rem;border-top:1px solid var(--et-line);padding-top:.5rem`; `149: margin-top:1rem`; `150: margin-top:1rem`; `151: margin-top:1rem` | 0 |
| checkout / `checkout/thanks.blade.php` | 13 | `21: max-width:980px`; `34: margin-top:.6rem`; `38: margin-top:.6rem`; `42: margin-top:1.75rem;border-top:1px solid var(--et-line);padding-top:1.5rem`; `44: margin-top:.8rem`; `52: margin-top:1rem;border-top:1px solid var(--et-line);padding-top:.75rem`; `59: margin-top:1.75rem;border-top:1px solid var(--et-line);padding-top:1.5rem`; `62: margin-top:.6rem`; `63: word-break:break-all`; `71: margin-top:.6rem`; `75: margin-top:1.75rem;border-top:1px solid var(--et-line);padding-top:1.5rem`; `77: margin-top:.6rem`; `78: margin-top:.75rem` | 0 |
| content / `content/show.blade.php` | 6 | `62: margin-top:1rem`; `89: margin-top:1rem`; `94: margin-top:1rem`; `125: margin-top:1.25rem`; `153: max-width:900px`; `158: margin-top:2rem` | 0 |
| fitment / `fitment/form.blade.php` | 18 | `50: margin-top:1rem`; `53: margin-top:1.4rem`; `75: margin-top:1rem`; `86: margin-top:1.5rem`; `87: margin-top:1rem`; `100: margin-top:1.25rem`; `101: margin-top:.65rem`; `103: width:100%;text-align:left`; `104: display:flex;align-items:center;justify-content:space-between;gap:1rem`; `105: display:block;margin-top:.25rem`; `112: margin-top:1.5rem`; `114: margin-top:1rem`; `142: max-width:48rem`; `143: margin-top:1.7rem`; `154: color:#fff`; `155: margin-top:1.7rem`; `169: display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1.4rem`; `177: margin-top:2.5rem` | 0 |
| pricing / `pricing/index.blade.php` | 2 | `44: margin-top:1rem`; `48: margin-top:1.25rem` | 0 |
| requests / `requests/create.blade.php` | 21 | `84: margin-bottom:1.2rem`; `93: margin-top:1.2rem`; `107: margin-top:1rem`; `118: margin-top:1.2rem`; `126: margin-top:1rem;padding:1rem`; `127: padding-inline:.3rem`; `169: margin-top:1rem;padding:1rem`; `170: padding-inline:.3rem`; `181: margin-top:1rem`; `188: margin-top:1.2rem`; `221: margin-top:1rem`; `228: margin-top:1.2rem`; `257: margin-top:1.1rem`; `258: margin-top:1.2rem`; `270: margin-top:.5rem;color:#fff`; `314: margin-bottom:1.2rem`; `318: margin-top:1.2rem`; `400: margin-top:.6rem`; `414: margin-top:1rem`; `415: margin-top:1.2rem`; `420: margin-top:1.2rem` | 0 |
| requests / `requests/thanks.blade.php` | 3 | `6: margin-inline:auto`; `7: margin-inline:auto`; `8: margin-top:1.5rem;text-align:left` | 0 |
| services / `services/show.blade.php` | 1 | `40: margin-top:1.5rem` | 0 |

Family totals: cart 12; catalogue 10; checkout 25; content 6; fitment 18; pricing 2; requests 24; services 1. The accepted audit had 110; Issues #28–#32 already removed 12, leaving the 98 above.

## `storefront-shapes.css` geometry/exception map

| Current owner | Current rule | Replacement owner |
|---|---|---|
| sharp profile | compatibility radius/shadow aliases on `.storefront[data-storefront-style='sharp']` | documented semantic storefront geometry/elevation tokens; compatibility aliases derive from them |
| admin bridge | `.et-admin-body` legacy radius aliases | retained as an explicit admin compatibility bridge; never used to shape storefront descendants |
| universal wildcard | every storefront descendant and both pseudo-elements forced to sharp radius with `!important` | **removed**; each control/surface/media component consumes the appropriate semantic token in its defining CSS rule |
| surface shadow list | final shadow override for cards, panels, media and maps | component definitions consume the storefront surface-shadow contract; no final override list |
| catalogue border list | final border override for product cards/filter panel | catalogue component definitions own their border |
| hover shadow list | final no-shadow override for buttons/buy/more | button/catalogue interaction definitions own the accepted hover state |
| functional-circle exception list | radios, cart count, filter counter, loading dot, spinner, stock dot and store-panel marker restored with `50% !important` | each genuine circle explicitly consumes `--et-radius-functional-circle` without `!important` |
| map-pin exception | pin silhouette restored with `!important` | `.et-map__pin` consumes the dedicated map-pin radius token in its defining rule |
| mobile drawer exception | filter sheets forced to sharp top geometry with `!important` | drawer/panel definitions consume the semantic drawer radius token in their own responsive rules |

Expected result: `storefront-shapes.css` is removed. The replacement profile/contract file contains tokens and semantic extracted classes only; it contains no universal selector and no specificity repair cascade.

## Predicted changed-file families

- Tokens/contracts: `design-tokens.css`, `app.css`, removal of `storefront-shapes.css`, new bounded storefront contract stylesheet.
- Existing component owners: `pro-design.css` and `storefront-v3.css` only where a literal radius/shadow/border currently defeats the semantic contract.
- Blade presentation hooks: the eleven storefront files listed in the inventory table.
- Architecture/regression checks: focused Issue #33 tests plus existing Laravel, buyer/admin Playwright and accepted visual-audit suites.
- Durable proof: `evidence/issue-33/` only.

No controller, route, translation, validation, price, catalogue, NDI, Dintero or other business/integration file is predicted to change.

## Protected baselines and comparison proof

- Exact accepted source baseline: `87509a644022a4df232c118d9bf4bab32f2c3683` (Issues #28–#32 merged).
- Issue #28 / PR #35: accepted single-focus interaction contract at merge `07ce99a2d09afdfe521f6150486aa98c799cef97`; re-run the accepted Issue #26 interaction audit.
- `evidence/issue-29/`: sticky header, footer disclosure and focus baselines.
- `evidence/issue-30/`: six-viewport home hero/process baselines and measured geometry JSON.
- `evidence/issue-31/`: services, service detail, request default/error/focus and battery flow baselines.
- `evidence/issue-32/`: six-viewport catalogue/product/cart states, checkout, filter open/draft/busy/error and geometry JSON.
- `evidence/issue-26/` plus `application/tests/Audit/issue-26-visual-audit.spec.ts`: remaining shared/content/pricing/contact/legal/404 and interaction coverage.

Post-change proof will use the same route/state fixtures at 1920, 1440, 1366, 1280, 390 and 320. Geometry measurements, horizontal-overflow assertions, computed radii and named interaction states will accompany screenshots. Pixel-level comparison may ignore raster anti-aliasing only; layout, focal geometry, crop and state semantics may not move.
