# Storefront mainline decision

Owner decision: 2026-09-23.

## Canonical version

Modern is the selected storefront and the canonical direction for all further
application work. The repository default `main` remains the intended final
development base. Until the cumulative integration PR is reviewed and merged,
`codex/customer-shared-followups` is the only canonical integration branch.

The cumulative integration PR is PR #84. It contains the Modern storefront,
the retained Classic profile, catalogue/snapshot/filter work, the shared
customer follow-ups, and the admin handoff. Its base is to be `main`; prior
stacked PRs are historical inputs and are not independent merge targets.

New feature/admin branches created before PR #84 is merged start from the latest
remote `codex/customer-shared-followups`. After PR #84 is merged, new work starts
from the updated `origin/main`. Do not start from the old `origin/main` tip,
`codex/autonomous-design-finish`, or `codex/classic-storefront` while the
cumulative PR is pending.

## Classic retention

Classic remains available at `https://staging-classic-easytire.bethub.fun` as a
customer comparison/reference and rollback-capable staging profile. It is not a
second product fork and receives no duplicate feature implementation.

Classic is preserved in three ways:

1. its scoped implementation remains in the shared source;
2. historical branch `codex/classic-storefront` remains in Git;
3. immutable tag `classic-staging-2026-09-17` identifies exact application SHA
   `11d799190a2e1863746f192983371e97f23526a7`, the deployed two-profile release
   before this mainline decision.

That SHA contains both profiles. Reproducing the old presentation requires the
server-side `STOREFRONT_DESIGN_PROFILE=classic`; the tag is not a separate old
business application. The Classic host retains its separate sessions/cache/
views/logs, canonical/noindex policy and admin redirect, while sharing the
database, catalogue, content and public media.

Do not delete the branch, tag, Classic host, current release or retained
predecessor as part of mainline consolidation. Retirement of the comparison
site, DNS or stored releases requires a later explicit owner decision.

## Development rule

- Implement admin, business behavior, catalogue and content changes once on the
  canonical line. Both storefront profiles consume the same application code
  and shared data.
- There is one admin/CRM on the Modern host. Classic `/admin` continues to
  redirect there.
- A Classic-only visual correction is still made on the canonical line under an
  explicit Classic profile scope. Do not create a long-lived Classic feature
  branch or cherry-pick every shared change into two branches.
- A Modern-only visual correction uses the Modern profile scope. Shared
  behavior must not depend on the visual profile.
- Preserve both profile tests until the owner explicitly retires Classic.

## Merge and runtime boundary

Retargeting PR #84 to `main`, documenting the decision and closing superseded
stacked PRs do not deploy anything. The two staging hosts remain on exact SHA
`11d799190a2e1863746f192983371e97f23526a7` until an authorized release replaces
it.

Do not push the feature history to the server's `main` ref: its legacy hook can
run backup/migration/seed work. A later reviewed staging release must use a
task-specific fail-closed helper, preserve both environments/profiles and
rollbacks, and follow the actual admin task's schema/data plan.

GitHub Actions is currently failing before job steps because of the account
billing/spending condition. This is not a test failure and is not permission to
claim CI green or bypass review. The owner has selected the product direction;
the PM still reviews and merges the single cumulative PR into `main`.
