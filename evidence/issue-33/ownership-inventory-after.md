# Issue #33 post-implementation ownership inventory

Implementation baseline: `87509a644022a4df232c118d9bf4bab32f2c3683`.

## Result

| Concern | Before | After |
|---|---:|---:|
| Storefront Blade `style=` attributes | 98 | 0 |
| Static inline presentation declarations | 98 | 0 |
| Genuinely data-driven inline presentation declarations | 0 | 0 |
| Universal storefront descendant/pseudo-element radius rules | 1 wildcard family | 0 |
| Storefront `border-radius: ... !important` repairs | 1 wildcard plus exception family | 0 |
| Final cascade shadow/border repair lists | 3 families | 0 |

Deterministic checks:

```powershell
rg -n --glob '*.blade.php' 'style\s*=' application/resources/views/storefront
rg -n "border-radius\s*:[^;}]*!important|\.storefront\[data-storefront-style='sharp'\]\s+\*" application/resources/css
```

Both commands return no matches.

## Final presentation owners

- `design-tokens.css` owns the semantic geometry contracts for surfaces, controls, media, drawers, functional circles and the map pin.
- `pro-design.css` and `storefront-v3.css` own component geometry, borders, elevation and interactive states at the component definition.
- `storefront-contracts.css` selects the accepted sharp storefront profile and owns the named, reusable layout hooks extracted from Blade.
- Storefront Blade templates now contain semantics, state and content only; no inline presentation remains.
- `storefront-shapes.css` and its final specificity-repair cascade were removed.

## Retained named exceptions

These are intentional shapes, not accidental rounding:

- functional circles: cart count, filter result counter, catalogue loading dot, spinner, stock dot and the store-panel decorative marker consume `--et-radius-functional-circle`;
- native radio controls retain `appearance: auto`, their accessible platform rendering and the same functional-circle token. Chromium reports `border-radius: 0px` for the native used appearance even though the authored token resolves to `50%`; the evidence asserts the token, native appearance and square control box instead of reintroducing `!important` or replacing the accessible control;
- the location marker consumes `--et-radius-map-pin`;
- mobile filter sheets consume `--et-radius-drawer`, which resolves to the accepted sharp value under the storefront profile;
- all former pill/chip/decorative rounding that was visually forced sharp by the accepted wildcard now consumes an appropriate surface/control token and therefore remains sharp without an override.

No dynamic inline style exception was retained because the audited templates contain no data-driven CSS value.

## Preserved result

The Issue #33 Playwright evidence covers 13 representative page/state families at all six accepted viewports (78 full-page screenshots), named interaction states and exact geometry comparisons against the accepted Issue #30–#32 JSON baselines. `baseline-geometry-comparison.json` reports `preserved: true` at every viewport and `surface-layouts.json` records no horizontal overflow.

