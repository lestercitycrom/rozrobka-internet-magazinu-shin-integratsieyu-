# EasyTire status

Updated: 2026-09-16 — Issue #79 autonomous public design finishing

## Current work

Owner requested a complete visual finishing pass after reviewing the live product, empty catalogue and seasonal form. Active branch `codex/autonomous-design-finish` starts at exact live `1326895e93b5f6bea67426100c282172be869564` (PR #78), preserving its snapshot and filter contracts. See [current scope](CURRENT_TASK.md) and [Issue #79](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/79). The entries below are predecessor evidence; they are not a claim about the new design preview.

Active work is [#77](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/77): finish filter consistency and adjacent browser checks. Branch `codex/catalog-filter-consistency` preserves active snapshot/design release `d6938f318052c58842bb66a610ea1bf7a2542943`. The previous #75/#76 snapshot activation is complete and its exact-head CI is fully green (run35051457360). Accepted `main` remains `3b8ae190512dd897cd0133aa3c2771d0e890196c`; older main is not the deployment source.

Latest pre-filter runtime read-back: 1,082 real tyres, 491 real wheels, one sandbox item; 3,168 real price rows and 16,128 stock rows unchanged, including observation hashes. Queue/scheduler running, no queued/failed jobs or in-flight imports; 30 complete backups and no partials. Snapshot prices display only, real commerce remains closed. Designer predecessor `274a1610829f87ed8521f65d62ac0789fc86ec28` retained. The #77 PR will record its own fresh release evidence, not reuse this read-back as proof of a later deployment.

Read-only supplier investigation: three succeeded XLSX receipts; real price/stock observations remain August 12, with zero fresh real commercial prices. Queue/scheduler operate, but this does not prove regular supplier delivery. SFTP password/chroot/list access was checked locally; actual NDI upload and recurring-import acceptance remain open. See [connection runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md).

Snapshot explicitly pauses file imports and offers historical calculated prices for display only; ordinary-product purchase remains closed. No refreshed timestamps, fake stock or changed commercial gates are part of this work. The former Issue #19 closeout below is historical, not an active second task.

Catalogue handoff complete: [PM5690161142](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5690161142). UI Polish starts from accepted main `3b8ae190512dd897cd0133aa3c2771d0e890196c` on `codex/ui-polish-v1`; the observed staging `95a1b76086e2d51e1aebb01911bc83a683bf78ea` has the same tree. See [current scope](CURRENT_TASK.md) and [report](../evidence/ui-polish/REPORT.md). The dated observations below remain history, not claims about a later preview.

## Accepted baseline

- GitHub `main` before the final handoff: `f25a005ac0277ce0ef03c303a2e7c9a997a83a48`.
- PR [#70](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/70) delivered catalogue visibility, freshness and purchase-safety recovery; its merged application release is `405f3f0a551c65d4ce13c5f1b192991ce3625371`.
- PR [#71](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/71) clarified the permanent staging demo's no-real-delivery copy; its merged release is `f25a005ac0277ce0ef03c303a2e7c9a997a83a48`.
- The accepted runtime report is [Issue #19 comment 5687006070](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5687006070); the PM verdict is [comment 5687038642](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5687038642).
- Production and DNS changes are not authorised.

## Runtime gates (last accepted staging read-back)

- Active application and staging Git: `f25a005ac0277ce0ef03c303a2e7c9a997a83a48`.
- Immediate retained predecessor: `405f3f0a551c65d4ce13c5f1b192991ce3625371`; an additional retained rollback is `980fdc117d27a7bcf5d0d0cd950e31e74cee1497`.
- Public catalogue: 1,082 real tyres, 491 real wheels and exactly one staging-only demo product.
- Queue worker and scheduler were running; jobs and failed jobs were `0 / 0`.
- Twenty complete gzip-valid database backups and five releases were retained, with no partial backup.
- Dintero remained in test mode. The permanent demo remained fixed at SKU `NDI-TEST-4765205551649102`, quantity `2`, unit `10 NOK`, total `20 NOK`, with no real delivery.
- The NDI inbox was configured and readable with three succeeded receipts and no failed/retryable receipt. No newer authoritative live stock observation than `2026-08-12 00:47:54` was present.

These are dated accepted observations, not a substitute for a fresh runtime read-back before a later deployment.

## Historical Issue #19 closeout (not current work)

- Issue [#19](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19) is reopened only for the bounded final handoff in [comment 5689353790](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5689353790).
- Branch: `codex/issue-19-final-handoff`, created directly from the accepted baseline above.
- Scope: add the localized price/availability contact action and explicit missing-price presentation on an unavailable real product detail, update the current pointers, run focused NB/RU checks and publish one final PR.
- No importer, stock, cart, checkout, payment, NDI, CRM or production behaviour is being reopened.

## Permanent catalogue boundary

Published active products may remain visible when stock is missing, stale or zero. Visibility never grants purchase eligibility. Cart, checkout and payment require a valid selling price and fresh, sufficient positive stock; the server-side guards remain authoritative. The staging-only demo is a separate fixed test contract and never enables a real payment, supplier order or delivery.

After the PM posts `CATALOG HANDOFF READY` and closes Issue #19, the final accepted `origin/main` is the sole design baseline. The designer must not assemble code from old PRs or branches.
