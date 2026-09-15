# EasyTire status

Updated: 2026-09-15 11:07 UTC
GitHub baseline: `origin/main` at `bf7f03e3d3265174e4e4307064769a742dd087cc`
PR #69 deployed application code: `980fdc117d27a7bcf5d0d0cd950e31e74cee1497`
Immediate staging rollback: `33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b`
Production: not authorised

## Environments

| Environment | State |
|---|---|
| GitHub `main` | Accepted baseline `bf7f03e3d3265174e4e4307064769a742dd087cc`. |
| Staging | PR #69 code `980fdc117d27a7bcf5d0d0cd950e31e74cee1497` is active for final review. The previous preview and accepted baseline are retained. |
| Production | No deployment or merge authorisation. |

## Runtime gates

| Gate | State |
|---|---|
| Dintero | Disabled on staging. Test configuration and callback protection are present; no payment was initiated by this handoff. |
| NDI catalogue | The catalogue/import boundary is preserved, but supplier stock is beyond the 2160-minute freshness limit. The fail-closed gate hides stale live stock. |
| NDI sandbox/write | Disabled on staging. The fixture remains present but inactive; no inquiry/order was sent. |
| Notion / Telegram | Disabled. Two pending outbox records are retained and were not sent. |
| Customer email | Not enabled by this handoff. |
| RU storefront | Hidden QA route with `noindex`; no public language selector. |

## Runtime snapshot

- Browser: `/`, `/dekk`, `/tjenester` and `/admin/login` returned HTTP 200 at 1440/390/320 where applicable, with zero page overflow, same-origin assets and zero browser errors.
- Services: queue worker and scheduler timer are active; both heartbeat ages were 0 minutes. Database-backed queue contains 0 jobs and 0 failed jobs.
- Deferred work: outbox pending 2 / failed 0; supplier-media pending 36 / failed 0. Their external gates remain off.
- Catalogue: 1585 products, 1574 published, 16129 stock rows. Latest observed stock is `2026-09-04 08:00:06`; 918 SKU pass catalogue rules before freshness and 0 after freshness.
- Booking configuration: 0 schedule intervals and 0 blackout periods. No available slots in this state is a configuration limitation, not a workday-view failure.
- Active staff-role account counts: Editor 1, Operations 1, Owner 2. No identities are recorded here.
- Backups: immediate previous code release and accepted baseline retained; runtime `.env` and shared storage preserved. There are 33 existing database backup files; latest snapshot is `2026-09-15 10:33:39 UTC`, 1,822,336 bytes. This code-only preview made no database mutation.
- Capacity: 5.2 GB was available after deployment.

## Current work

- Issue #44 implementation and exact-SHA staging preview are complete in PR #69.
- Independent visual acceptance, merge and production deployment remain separate decisions.
- Further functional development is stopped for this handoff.
- Canonical details: `evidence/issue-44/design-v2-result.md`, `evidence/issue-44/design-v2-qa-register.md` and `docs/operations/OPERATIONS_DAY_AND_CLIENT_SEARCH.md`.

This file is the short current snapshot. Historical detail belongs to merged Issues, PRs and the linked evidence.
