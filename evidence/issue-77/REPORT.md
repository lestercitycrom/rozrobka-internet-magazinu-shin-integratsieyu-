# Issue 77 — Catalogue filter consistency

Date: 2026-09-16. Scope: [Issue #77](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/77). Base: active snapshot/design `d6938f318052c58842bb66a610ea1bf7a2542943`. This report distinguishes implementation from the exact-head CI and later runtime proof recorded in the PR.

## Confirmed defects and fixes

| Scenario | Cause | Correction |
|---|---|---|
| Homepage125 → catalogue shows Alle | Broad homepage size vocabulary and narrower published-product facets; a selected query value could have no option | Valid requested values are retained as enabled selected options. No match still means an honest empty result. |
| Profile60, rim20, PCD120 | Nested `rtrim` removed integer zeros, not just fractional padding | Canonical decimal parsing strips fractional zeros only, and normalizes query and options together. |
| ET0 | Active-chip filtering and query `when()` treated string0 as absent | Preserve0 in state/chips; apply the actual zero-offset database predicate. |
| Decimal URL and browser history | AJAX assigned raw URL values to older option sets | Copy validated selected values and options from the rendered response, preserving the current form/focus/drawer. |
| Sort chosen after first page load | Original filter form did not have a hidden sort field | Always include/synchronize sort, including an empty reset value. |
| Malformed or irrelevant parameters | Query validation and displayed filter state were separate | One applied state builds the query, controls, chips, sort and pagination links. Escape retained unknown text. |

The homepage vocabulary is intentionally not reduced to current stock or this historical file set. No claim of availability is added. Source profile0 denotes missing/non-meaningful dimensional data and is not offered as a usable tyre profile; stored supplier rows are untouched.

## Verification

- Dedicated consistency tests plus existing public catalogue and snapshot pricing tests: 59 passed, 540 assertions.
- Frontend build completed; application/design CSS remains unchanged.
- Independent source review checked server/client consistency and corrected a test regex, with no concrete JS regression found.
- New hermetic one-worker Playwright configuration covers NB/RU ×1440/390/320; a separate SQLite database and storage are guarded before fixture reset. All integration gates are disabled. Final results and screenshot review are added to the PR after the run.
- Existing full buyer/admin regression, snapshot safety, MySQL and history secret scan remain mandatory in final candidate CI.
- New release helper offline tests cover current/branch/scope guards, byte-exact `.env` copy, shared environment isolation, fresh backup validation, importer observation failure and atomic rollback.

Adjacent-page audit is read-only: home, tyres, wheels, fitment entry, services, prices, contact, about, empty basket and admin login. Remote mode is explicitly restricted to approved staging; non-GET and third-party requests are blocked. This does not validate live checkout, external map tiles or external integrations.

## Deployment and remaining boundary

Owner-authorised bounded staging preview only. The helper has no migrations, staging seeds/imports, pruning, environment setting changes or service restart. Final published SHA must pass checks; a fresh backup and retained snapshot predecessor are required. Read-only post-deploy comparisons must prove real price/stock observations and effective configuration unchanged.

Historical prices remain display-only; real-product cart/payment stays closed and the existing fixed demo contract is unchanged. Regular supplier delivery, file schema/cadence acceptance and identical-content delivery/replay semantics remain external work in the [NDI runbook](../../docs/operations/NDI_DELIVERY_CONNECTION.md). Completion of this filter issue does not mean acceptance of that feed or of the whole site.
