# Current task

- Issue: [#77 — Catalogue filter consistency](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/77).
- Branch: `codex/catalog-filter-consistency`, based on active snapshot/design release `d6938f318052c58842bb66a610ea1bf7a2542943` (#75 / #76).

Owner continuation, 2026-09-16: finish the reported width=125 mismatch and inspect related scenarios, rather than stopping after diagnosis. Preserve the designer's current pages and the explicit saved-price staging mode.

Scope: one validated filter state for query, options, chips, sort and links; preserve valid requested values absent from catalogue facets; fix trailing integer zero loss and ET=0 filtering; synchronize AJAX/history with server-rendered canonical controls. Cover homepage handoff, tyres/wheels, sort/filter/reset/history, mobile draft/error behaviour and NB/RU. Independently inspect adjacent pages without live submissions.

No supplier/price/stock mutation, migrations, seeds on staging, new integration calls, production/DNS or payment activation. The owner authorises the bounded staging correction after focused checks, with a fresh backup and retained rollback. Final PM/design and external supplier-feed acceptance remain separate.

Previous snapshot work is complete on staging; its final exact-head [CI is green](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/35051457360). Live NDI recurring delivery is still unaccepted: see the [connection runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md).
