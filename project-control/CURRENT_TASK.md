# Current task

Active owner continuation: [#79 — Autonomous public design finishing](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/79), branch `codex/autonomous-design-finish`, based on exact current staging/PR #78 head `1326895e93b5f6bea67426100c282172be869564`. Finish the complete public buying/booking experience, including product controls, honest empty states, field grouping and cross-page visual rhythm. Preserve all #75/#77 snapshot/filter functionality and the fixed two-item sandbox. Public presentation only; no migrations, staging seeds, catalogue edits, integration activation or main merge. Design previews use the existing #44 published-SHA permission and retain the immediately previous release.

The handoff below records the functional predecessor, not a second active task.

- Issue: [#77 — Catalogue filter consistency](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/77).
- Branch: `codex/catalog-filter-consistency`, based on active snapshot/design release `d6938f318052c58842bb66a610ea1bf7a2542943` (#75 / #76).

Owner continuation, 2026-09-16: finish the reported width=125 mismatch and inspect related scenarios, rather than stopping after diagnosis. Preserve the designer's current pages and the explicit saved-price staging mode.

Scope: one validated filter state for query, options, chips, sort and links; preserve valid requested values absent from catalogue facets; fix trailing integer zero loss and ET=0 filtering; synchronize AJAX/history with server-rendered canonical controls. Cover homepage handoff, tyres/wheels, sort/filter/reset/history, mobile draft/error behaviour and NB/RU. Independently inspect adjacent pages without live submissions.

No supplier/price/stock mutation, migrations, seeds on staging, new integration calls, production/DNS or payment activation. The owner authorises the bounded staging correction after focused checks, with a fresh backup and retained rollback. Final PM/design and external supplier-feed acceptance remain separate.

Previous snapshot work is complete on staging; its final exact-head [CI is green](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/35051457360). Live NDI recurring delivery is still unaccepted: see the [connection runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md).
