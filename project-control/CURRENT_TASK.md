# Current task

Owner decision, 2026-09-23: Modern is the canonical storefront and intended
`main`; Classic remains available in Git and on its staging subdomain as a
retained profile, not a parallel feature branch. PR #84 is the single cumulative
integration PR to `main`. New admin work starts from its latest integration head
until merge, then from updated `origin/main`. See
[`STOREFRONT_MAINLINE.md`](STOREFRONT_MAINLINE.md) and
[`ADMIN_AGENT_HANDOFF.md`](ADMIN_AGENT_HANDOFF.md). This decision lifts the
design wait in Issue #81 but does not itself authorize deployment or live data
mutation.

The following records the prior admin coordination handoff.

Next-agent coordination, 2026-09-18: any new admin/CRM task starts from the
current shared two-profile line, not `origin/main` or a historical design branch.
Read and follow [`ADMIN_AGENT_HANDOFF.md`](ADMIN_AGENT_HANDOFF.md) before editing.
The handoff authorizes a fresh branch and isolated local work only; its presence
does not authorize a staging deployment, database mutation or integration use.

The following records the completed design-balance task.

Owner follow-up, 2026-09-17: on the same `codex/customer-shared-followups`
branch and deployed base `b25bc47621c08629b7f673410e207087e85234de`, fix
Classic heading diacritic/line collisions and give Modern stronger headings
and a subtly structured vehicle finder. Preserve body-text readability,
profile differences and all shared customer functionality. CSS-only public
presentation; no database/catalogue/integration changes or PR merge. Verify
locally and on both staging profiles, retaining each `b25bc47` release for
rollback. GitHub Actions remains billing-blocked; do not claim cloud CI green
or change billing/branch restrictions. Prior source-request audit is complete
except the customer's real Snapchat URL and unavailable voice attachments.

The following records the completed shared-customer changes.

Owner continuation, 2026-09-17: implement the shared functional/content requests
from `ChatExport_2026-09-16` on both live design profiles. Work branch
`codex/customer-shared-followups`, based on Classic/source `f776dbe…`. Preserve
the profile-specific typography, finder layout, red accents and media proportions.
Scope: content/service search, card-local descriptions, catalogue quantity
selection (sandbox remains fixed at two), contact navigation and partner logos.
Snapchat remains visible with a localized "link coming soon" placeholder until
the owner saves the verified customer account URL in the existing admin setting.
Do not invent a destination. No main
merge, catalogue changes, migrations/seeds or integration activation. The two
staging releases must retain their environments, isolation and rollback versions.

The following records the completed split and its predecessors.

Owner continuation, 2026-09-17: create the separate Classic preview on
`staging-classic-easytire.bethub.fun`, branch `codex/classic-storefront`, from
the functional source `7165ca13090e3f0f122b181cc6c37eb6fc9f874f`. Restore the
pre-redesign visual treatment selectively from `bf7f03e…`; retain modern runtime
`5472266…` at its current host. Shared database/catalogue, isolated runtime
sessions/cache, no migrations/seeds/integration activation or merge. Owner has
authorised this second-host preview; prior PR80 local-only instruction remains
historical. See [boundary and verification](../evidence/classic-preview/README.md).

The following records the accepted functional/design predecessors.

Active owner continuation: [#79 — Autonomous public design finishing](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/79), branch `codex/autonomous-design-finish`, based on exact current staging/PR #78 head `1326895e93b5f6bea67426100c282172be869564`. Finish the complete public buying/booking experience, including product controls, honest empty states, field grouping and cross-page visual rhythm. Preserve all #75/#77 snapshot/filter functionality and the fixed two-item sandbox. Public presentation only; no migrations, staging seeds, catalogue edits, integration activation or main merge. Design previews use the existing #44 published-SHA permission and retain the immediately previous release.

Current bounded follow-up: close only R2b from the [latest PR #80 review](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/80#pullrequestreview-5226165027). Mobile current-state underlines its label, not the arrow or disclosure signs. R1/R3/R4 and routing semantics are accepted and must remain unchanged. See [targeted verification scope](../evidence/pr80-r2b/README.md). Local-only; no new redesign, runtime deployment or merge.

The handoff below records the functional predecessor, not a second active task.

- Issue: [#77 — Catalogue filter consistency](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/77).
- Branch: `codex/catalog-filter-consistency`, based on active snapshot/design release `d6938f318052c58842bb66a610ea1bf7a2542943` (#75 / #76).

Owner continuation, 2026-09-16: finish the reported width=125 mismatch and inspect related scenarios, rather than stopping after diagnosis. Preserve the designer's current pages and the explicit saved-price staging mode.

Scope: one validated filter state for query, options, chips, sort and links; preserve valid requested values absent from catalogue facets; fix trailing integer zero loss and ET=0 filtering; synchronize AJAX/history with server-rendered canonical controls. Cover homepage handoff, tyres/wheels, sort/filter/reset/history, mobile draft/error behaviour and NB/RU. Independently inspect adjacent pages without live submissions.

No supplier/price/stock mutation, migrations, seeds on staging, new integration calls, production/DNS or payment activation. The owner authorises the bounded staging correction after focused checks, with a fresh backup and retained rollback. Final PM/design and external supplier-feed acceptance remain separate.

Previous snapshot work is complete on staging; its final exact-head [CI is green](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/35051457360). Live NDI recurring delivery is still unaccepted: see the [connection runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md).
