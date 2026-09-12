# CUMULATIVE PREDEPLOY CHECKPOINT — Issue #44

Date: 2026-09-13
Runtime authorisation: none
Deployment decision: not requested by this checkpoint

## Exact revisions

- Accepted cumulative application base: `7d7c6c4ad58dca8cd651e7b88bcee2bcaa7eed92`.
- QA/correction branch: `codex/issue-44-cumulative-release-acceptance`.
- Draft review: PR #51.
- Staging application revision read back before this pass: `f70f358f06af384364973951eaab8d4fac2b2203`.
- Immediate retained rollback revision: `ee512d723e78a65a89bfdee4268c3a779f632b33`.
- Older retained rollback revision: `01a931d432b32a5a0633d1b508256b225387d429`.
- Final QA head: the exact commit containing this checkpoint is recorded in the PR `ENGINEER UPDATE` (a committed document cannot embed its own resulting commit hash).

## Cumulative QA result

The remaining PM acceptance gaps are now covered:

- a natural full settings-form save/reload with every unrelated control enabled;
- NB/RU admin controls at 1024, 390 and 320 through real keyboard traversal;
- Dekkhotell reverse Shift+Tab, Escape/focus return, real 390/320 touch, mobile CTA reachability, and a native no-JavaScript fallback;
- native-resolution poster-only evidence at 1440/390/320 in NB/RU;
- the accepted Issue #45–#47 evidence for V45 viewport families, long short copy, legacy body-only copy, video failure, partner layout, customer navigation and the final homepage composition.

Results are indexed in `evidence/issue-44/README.md`. No new P1/P2 application or visual defect remains after the two bounded corrections below.

## Bounded corrections found by predeploy QA

### 1. Staging robots response

The live staging domain currently returns a crawlable body (`Disallow:` with no path). A tracked static `public/robots.txt` is served before the environment-aware Laravel route, so the correct non-production `Disallow: /` response is bypassed.

Correction in PR #51:

- remove the conflicting static file;
- retain the environment-aware route;
- add a regression that requires the static file to be absent and the non-production response to contain `Disallow: /`.

Production behaviour remains environment-aware. The post-deploy acceptance must read the real response body, not only status 200.

### 2. Issue #45 content seeder wiring

The deployment hook invokes the general `DatabaseSeeder`, while the accepted guarded navigation-copy seeder was only documented as an explicit command. Before this correction, a current empty staging settings table would render the new translation fallback after Issue #47 created its settings row, but the exact corrected values would not be persisted. The repeatable deployment path was also incomplete for any environment that already carried the exact legacy persisted hero values, because those stored values override the fallback.

Correction in PR #51:

- invoke `CustomerNavigationRevisionSeeder` immediately after the legacy homepage content seed;
- make the Issue #47 composition manifest persist the corrected NB/RU values when it creates a previously absent settings row, recording `null -> corrected value` for guarded rollback;
- prove through the full production-seed path that both a missing row and exact legacy NB/RU values end with the exact persisted and rendered copy;
- prove that a repeat run preserves an administrator edit made in only one language;
- retain the seeder's exact-old-value guard, idempotence, and operator-edit preservation.

## Fresh redacted staging read-back

| Area | Read-only result | Release implication |
|---|---|---|
| Current release | `f70f358f06af384364973951eaab8d4fac2b2203` | Candidate is not deployed. |
| Candidate migrations | The Issue #46 and #47 migrations/columns/tables are absent. | Expected; the protected hook must apply them before activation. |
| Database/cache | Reachable; both configured for database-backed operation. | Basic dependency check passed. |
| Jobs/failed jobs/outbox | 0 / 0 / 0 pending or failed. | There is no work to drain, but zero work does not make the services healthy. |
| Queue worker | Inactive; heartbeat stale. | Runtime blocker before acceptance. Diagnose the repeated stop before recovery. |
| Scheduler | Timer failed/stale; last service execution itself exited successfully. | Runtime blocker before acceptance. Determine the resource/start failure and prove repeated healthy ticks. |
| NDI | Last import is far beyond the freshness window; live write/read gates and sandbox API gates are disabled. | Correct fail-closed storefront behaviour must remain. Do not publish stale stock. |
| Payments | Commerce gate disabled; Dintero configured in test environment. | No payment test or real payment is authorised by #44. |
| Notifications | Customer delivery, Notion and Telegram disabled; mail uses a non-delivery transport. | Must remain disabled for this staging release. |
| Disk | About 8.9 GiB available, 77% used; health threshold currently passes. | Sufficient for one release, but retain only according to the approved release/backup policy. |
| Inodes | About 81% free. | No inode pressure. |
| HTTP smoke | Homepage, admin login and robots endpoint return 200. | Robots body is still wrong until the correction is deployed and verified. |

The queue and scheduler state is a runtime blocker, not an application-test failure. The final deployment gate must include cause-based recovery and post-recovery heartbeats; a blind one-off restart is insufficient.

## Exact content effect on the current staging snapshot

The hook takes a database backup, runs the two additive migrations, and then runs `DatabaseSeeder` before atomically activating the new application revision.

Expected scoped result:

1. Issue #45: because staging currently has no settings row, the guarded navigation seeder initially has no persisted value to change. The Issue #47 composition manifest then creates the row, stores the exact corrected NB/RU hero copy and journals the original `null` values. If an environment already has the exact legacy text, the navigation seeder updates only that language; unrelated/operator text is preserved. Repeat runs do not overwrite a later one-language administrator edit.
2. Issue #46: the single existing workshop promotion is adopted under its stable key. Its current title/body and related operator differences are preserved, so it remains operator-owned (`manifest_hash = null`) and is not duplicated. Three other offers are created, giving four total offers. Twelve managed service cards and five local homepage media assets are created.
3. Issue #47: one settings row is created and only blank/recognized legacy composition fields are filled; four partner rows and four local logo assets are created; the base `hotel-r15` amount changes from 750 to 700 NOK with a field-level journal; the exact missing video remains unassigned and the approved poster remains visible; Snapchat remains omitted because no confirmed destination exists.
4. A repeat `DatabaseSeeder` run is idempotent. Later administrator edits are not reclassified as untouched manifest data.

Synthetic proof: 25 focused seed/apply/repeat/operator-edit/rollback tests pass with 158 assertions. The final full Laravel/Pest matrix passes 466 tests with 1 expected skip and 4,405 assertions; the accepted broader suite also covers the public NB/RU result. A pre-existing catalogue assertion was made order-independent by checking gross/net price values by their tax-mode keys; no application pricing behaviour changed.

## Apply plan after an explicit `STAGING_DEPLOY_APPROVED`

1. Freeze administrative content edits for the short staging window and record the exact accepted PR head and current staging/rollback revisions.
2. Resolve the queue-worker and scheduler failure by cause, then prove fresh repeated heartbeats, zero failed jobs, and an empty or intentionally drained outbox. Stop if they become unhealthy again.
3. Confirm the deployment hook can create and verify its encrypted pre-migration database backup. Stop on any backup failure.
4. Merge only the exact PM-accepted PR head into `origin/main`; verify the resulting exact main SHA and successful GitHub quality gates.
5. Push that exact main SHA through the protected staging remote once. The hook performs locked dependency installation, production build, Laravel tests, backup, forced migrations, general guarded seed, cache compilation, and atomic release activation.
6. Read back the active revision and verify `/`, `/admin/login`, `/robots.txt` body, NB/RU homepage composition, poster-only Dekkhotell, content counts, 700 NOK base hotel price, and the preserved legacy workshop offer.
7. Re-check database/cache, queue and scheduler heartbeats, jobs/failed jobs/outbox, disk/inodes, NDI freshness, and every disabled integration gate. No payment, NDI order/inquiry, notification, or real customer submission is part of this acceptance.

Stop before or during the hook on any revision mismatch, backup failure, failed test/build/migration/seed, unexpected content conflict, or runtime-health regression. Do not manually force the symlink or bypass the hook.

## Rollback plan

If failure happens before activation, the previous application revision remains active. If migrations or seeds have already changed the shared database, stop traffic-changing work and use the verified pre-migration backup under an explicit recovery approval; never use blind destructive migration rollback.

If live acceptance fails after activation:

1. keep integrations disabled and prevent new real submissions;
2. run the guarded rollback seeders from the accepted release in reverse content order:
   - `RollbackCustomerHomepageCompositionSeeder`;
   - `RollbackCustomerHomepageCollectionsSeeder`;
   - `RollbackCustomerNavigationRevisionSeeder`;
3. verify that untouched Issue #46/#47 rows are drafted, the owned 700 NOK change is restored, unchanged owned settings are restored, and all later operator edits plus media remain intact;
4. atomically return to the retained previous application release;
5. leave additive schema in place unless a separate reviewed database recovery authorises restoring the verified backup;
6. re-run the complete redacted health and HTTP read-back.

The rollback seeders are intentionally non-destructive: they do not delete customer/operator content or media and do not overwrite later administrator changes.

## Explicit non-code decisions/materials

- Exact `003 muzik.mp4`: not supplied; poster fallback is accepted for staging, but the original media requirement remains open.
- Snapchat: no confirmed username/URL; no link is published.
- September offer dates contain day/month copy but no year or database schedule. For the 2026 staging review they are displayed as supplied. Before production, the owner/customer must confirm the intended year/recurrence and the manual publish/unpublish date; the system must not invent annual recurrence.

## Gate recommendation

Application QA can proceed to exact-head PM review after commit, push and green CI. Staging deployment must remain unapproved until:

- the PM accepts the exact corrected head;
- queue/scheduler cause and recovery plan are accepted;
- a one-time `STAGING_DEPLOY_APPROVED` is issued.
