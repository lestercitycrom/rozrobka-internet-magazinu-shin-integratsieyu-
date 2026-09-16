# Issue #75 — explicit historical catalogue preview

Date: 2026-09-16. Review diff against preserved designer preview `274a1610829f87ed8521f65d62ac0789fc86ec28`. Exact committed/deployed head and runtime evidence are recorded in the single PR linked from [Issue #75](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/75).

## Completed local checks

- Full Laravel suite: **628 passed, 1 Windows symlink test skipped; 5773 assertions**. Hermetic SQLite, external integrations disabled.
- Dedicated snapshot Playwright: **18 passed**; NB/RU at 1440, 390 and 320. Historical tyre/wheel listing/detail, source dates, no real purchase controls or Offer schema, direct cart rejection, preserved sandbox cart, localized owner health and no horizontal overflow. Local fixture-role setup was corrected before the final green run.
- Frontend build passed. Existing designer CSS/JS are not modified.
- Deployment safety suite passed: scope/SHA/environment guards, isolated build/cache, fresh backup requirements, corrupt/missing backup rejection, health/revision rollback, exact `.env` line preservation, old-importer refusal. Mock tests do not connect to staging.
- Independent read-only agent audit found no blocking price-mode/CLI/health/deployment isolation defects. Added a preactivation old-importer check; an observation is not a global scheduler lock, so verify source observations after activation as well.
- The first full-history CI secret scan flagged two 2026-08-01 status-prose lines in an old commit. Independent inspection confirmed neither contains credentials; two exact commit/file/rule/line fingerprints were added to `.gitleaksignore`. No broad exclusion or history rewrite. The application tree is unchanged by this CI-only follow-up.

## Reproduce

From `application` with an available PHP executable:

```text
php artisan test --compact
npm run build
npx playwright test --config=playwright.snapshot.config.ts
```

From repository root: `bash deploy/tests/staging-catalog-snapshot-safety.sh` and `php scripts/validate-project-control.php`.

Playwright uses only its guarded `snapshot-e2e.sqlite` local fixture, one worker and disabled external calls. Set `PLAYWRIGHT_PHP_BINARY` if PHP is not on PATH. `SNAPSHOT_LIVE_URL=https://staging-easytire.bethub.fun` changes this matrix to public read-only checks; local cart/admin mutation tests then explicitly skip. Screenshots are written under `application/tmp/snapshot/playwright-results` for each named viewport/locale.

## Acceptance boundaries

No import/replay/timestamp update is used to display historical prices. Snapshot is not supplier feed acceptance; paused inbox is intentional but raw stale source health remains visible. Invalid modes and production snapshot fail closed. The existing active pricing rule is reused, and source costs are not directly exposed.

The [NDI connection runbook](../../docs/operations/NDI_DELIVERY_CONNECTION.md) distinguishes the locally verified receiving SFTP from unverified provider delivery, exact live file schema/cadence, and the known identical-content/new-delivery versus replay limitation. These remain prerequisites for the live transition, not hidden completed work.
