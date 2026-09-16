# Current task

- Issue: [#75 — Staging catalogue snapshot and NDI delivery readiness](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/75).
- Branch: `codex/staging-catalog-snapshot`, stacked on the deployed designer preview `274a1610829f87ed8521f65d62ac0789fc86ec28` from #73 / #74. No design rollback or unrelated merge.

Owner decision, 2026-09-16: implement and activate an explicit staging mode that displays dated prices from the already imported supplier files while automatic file import is paused. This is a new staging policy, not evidence that the supplier feed was previously accepted or intentionally deferred.

Scope: `NDI_CATALOG_MODE=snapshot|live`; separate historical display pricing from strict commerce; localized source-date notices; importer pause and truthful admin health; hermetic tests and protected no-migration staging release. Keep original observations, active markup policy and fixed sandbox contract unchanged. Preserve prior/shared runtime configuration and retained design release.

End-to-end supplier delivery remains unaccepted. [Connection runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md) records locally verified SFTP, the original provider request, actual schema/cadence acceptance, and the unchanged-content delivery/replay limitation. No provider message, credentials transmission, production/DNS or commercial integration is authorised by this task.
