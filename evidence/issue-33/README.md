# Issue #33 evidence

This package proves that storefront presentation ownership was consolidated without redesigning or moving the accepted Issues #28–#32 result.

## Inventory

- `ownership-inventory-before.md`: every pre-change inline declaration, wildcard rule, exception and protected baseline.
- `ownership-inventory-after.md`: deterministic before/after counts and the final owner/exception map.

## Browser proof

`after/` contains:

- 78 full-page JPEGs: 13 key storefront surfaces at 1920×1080, 1440×900, 1366×768, 1280×800, 390×844 and 320×800;
- `surface-layouts.json`: HTTP status and horizontal-overflow measurements for every capture;
- `baseline-geometry-comparison.json`: exact protected geometry comparison for the home hero, service-detail composition, empty cart and product purchase composition at all six viewports;
- `interaction-and-geometry-states.json`: default, hover, keyboard focus, sticky-header, desktop filter, mobile drawer, disabled, error, selected native radio and map-state evidence;
- focused screenshots for sticky-header, disabled filter and filter-error states.

The synthetic evidence environment disables payment and NDI submissions and uses a disposable SQLite database.

Run the proof from `application/`:

```powershell
$env:PLAYWRIGHT_PHP_BINARY='C:\OSPanel\modules\PHP-8.3\php.exe'
& 'C:\Users\dmcpc\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' node_modules\@playwright\test\cli.js test --config=playwright.issue-33.config.ts --workers=1
```

Expected result: 3 tests pass. On this Windows host the Laravel web-server wrapper may remain open after Playwright prints all green results; stopping that already-completed wrapper does not change the test result or evidence.

