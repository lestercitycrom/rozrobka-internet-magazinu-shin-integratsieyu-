# Issue 41 admin visual coherence evidence

This directory is the deterministic evidence set for the authenticated admin audit required by Issue #41.

- `before/`: baseline measurements, contact sheets, selected native screenshots, and the seven-viewport dashboard set captured before remediation.
- `after/`: final measurements/views, the matching seven-viewport dashboard set, the NB/RU dashboard state matrix, and selected acceptance-state evidence.
- `surface-inventory.md`: explicit route/family/state coverage.
- `findings.md`: atomic finding register with acceptance checks and resolution status.

The browser fixture is synthetic, local/testing only, and disables NDI submission, payments, mail delivery, and external inquiry calls. The dashboard state route is additionally gated by `E2E_ADMIN_VISUAL_STATES_ENABLED=true`, is absent by default, and supplies view-only counts without database or business-metric mutation. Raw full-page screenshots stay in `application/tmp/issue-41/`; the compact reviewable subset is copied here.

Run the audit from `application/`:

```powershell
$env:PLAYWRIGHT_PHP_BINARY='C:\OSPanel\modules\PHP-8.3\php.exe'
$env:ISSUE_41_EVIDENCE_MODE='before' # or after
.\node_modules\.bin\playwright.cmd test --config=playwright.issue-41.audit.config.ts
```

The seven required viewport projects are 1920×1080, 1440×900, 1366×768, 1280×800, 1024×768, 390×844, and 320×800. The final `after` run also enforces no document overflow, no clipped controls, no unresolved translation keys, named keyboard-focusable table regions, the zero/low/large KPI contract, real keyboard order, and representative validation/guard/confirmation/empty/long-content states.

Final dedicated local result: **7/7 Playwright projects passed**. The durable proof includes:

- `before/dashboard/` and `after/dashboard/`: dashboard at all seven required viewports;
- `after/dashboard-states/`: zero, low, and large values in NB/RU at 1440/1024/390/320;
- `after/acceptance-states/`: selected keyboard, validation, guard, confirmation, empty, and long-content screenshots;
- `after/*-acceptance-states.json`: exact selectors, order, values, tones, columns, and geometry;
- `after/selected/`: native-resolution dashboard, request lifecycle, health, dense-record, hover/focus, and table evidence.
