# Phase 1 pre-deployment checkpoint

- Application candidate: `f70f358f06af384364973951eaab8d4fac2b2203`.
- Previous accepted staging application: `ee512d723e78a65a89bfdee4268c3a779f632b33`.
- Runtime authorisation: `NONE`.
- Deployment performed: no.

## Result

`BLOCKED — RUNTIME DECISION REQUIRED`

The cumulative application candidate is quality-green, but staging does not yet satisfy the PM-defined safe deployment preconditions. No deploy approval is requested.

## Exact source and migration boundary

- Branch was created from the exact cumulative main application SHA above; the QA branch contains no application change.
- Draft review: PR #43.
- Candidate versus current accepted staging contains no migration-file delta.
- Read-only staging migration status showed every known migration applied and no pending entry.

## Read-only staging checkpoint

- `current/REVISION` matched the previous accepted staging application SHA above.
- The atomic `current` link resolved to that release; the previously accepted rollback SHA `01a931d432b32a5a0633d1b508256b225387d429` remains retained.
- Root disk and inode headroom were above the documented thresholds.
- Database, database cache, scheduler heartbeat, failed-job count and supplier-media failure count were healthy.
- The queue worker heartbeat was stale, no supervised worker was active, and the database queue was non-empty. The queue was not run, cleared or changed.
- The known stale NDI feed condition remains external-blocked under Issue #19 and continues to fail closed.
- Homepage, admin login and robots smoke returned HTTP 200 with the expected content types.

## External gates

- Email delivery, Notion, Telegram and live NDI order mutation were disabled; mail remained on the non-delivery log transport.
- Test Dintero payments and the restricted NDI sandbox/inquiry path were enabled. This does not match the PM-required all-disabled checkpoint.
- No customer form, service request, order, payment, NDI request, email, Telegram message or Notion write was executed.

## Exact-candidate quality

- Project-control validation: passed.
- Full Pint check: passed.
- Production frontend build: passed.
- Full Laravel regression: 423 passed, 1 intentional skip, 3917 assertions.
- Standard buyer/admin Playwright matrix: all 197 positions completed without a failing test body (156 passed, 41 project-specific skips). The Windows runner shell required termination after its local web server had exited and the final test had completed.
- Accepted storefront visual suite: all 102 positions completed without a failing test body (61 passed, 41 matrix-specific skips). The same Windows runner-shell teardown caveat applies.
- Authenticated admin audit: 7 of 7 viewport projects passed. The same Windows runner-shell teardown caveat applies.
- GitHub Actions run 64: Application quality passed; reachable-history secret scan passed.

## Deployment and rollback plan

- Deployment, when separately authorised, pushes only the exact application SHA to the established staging main remote. The existing locked hook creates a new release, installs locked dependencies, builds and tests, takes a database backup, applies any safe migration, refreshes caches, then atomically replaces `current`.
- The current accepted release and exact rollback release remain preserved. Rollback uses an atomic `current` switch to the verified retained release followed by cache/worker verification; no destructive schema rollback is planned.
- No deployment command, rollback command, credential or unrestricted server path is stored in this evidence.

## Required PM decision

Provide one bounded runtime plan for:

1. restoring and supervising the staging queue worker, then verifying the pending queue drains safely without external delivery;
2. disabling the test payment and NDI sandbox/inquiry gates for the deployment checkpoint, or explicitly replacing the all-disabled requirement with an exact safer acceptance rule.

After that authorised runtime work, repeat the read-only health and external-gate checks. Only a green repeat may advance to `STAGING_DEPLOY_APPROVED`.
