# Issue 41 admin visual coherence evidence

This directory is the deterministic evidence set for the authenticated admin audit required by Issue #41.

- `before/`: baseline measurements, contact sheets, and selected native screenshots captured before remediation.
- `after/`: the same measurements and views after remediation.
- `surface-inventory.md`: explicit route/family/state coverage.
- `findings.md`: atomic finding register with acceptance checks and resolution status.

The browser fixture is synthetic, local/testing only, and disables NDI submission, payments, mail delivery, and external inquiry calls. Raw full-page screenshots stay in `application/tmp/issue-41/`; the reviewable subset is copied here.

Run the audit from `application/`:

```powershell
$env:PLAYWRIGHT_PHP_BINARY='C:\OSPanel\modules\PHP-8.3\php.exe'
$env:ISSUE_41_EVIDENCE_MODE='before' # or after
.\node_modules\.bin\playwright.cmd test --config=playwright.issue-41.audit.config.ts
```

The seven required viewport projects are 1920×1080, 1440×900, 1366×768, 1280×800, 1024×768, 390×844, and 320×800. The final `after` run also enforces no document overflow, no unclipped controls, no unresolved translation keys, and labelled keyboard-focusable table regions.

Final local result: **7/7 Playwright projects passed**. The `after/selected/` directory contains native-resolution review evidence for the dashboard, request lifecycle, health semantics, mobile dense-record layouts, hover/focus behavior, and keyboard table access.
