# Current review

Current scope: [Issue #75](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/75), branch `codex/staging-catalog-snapshot`, base designer preview `274a1610829f87ed8521f65d62ac0789fc86ec28`. The snapshot PR and exact candidate SHA are linked in Issue #75 after checks. This task does not independently accept design PR #74.

- PR: [#76 — snapshot catalogue](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/76); the older #72 below is retained history only.

Required current invariants:

- Snapshot only on staging/local/testing; unknown mode or production snapshot fails closed.
- Positive dated historical source and the existing active rule for display only; no timestamp rewrites, supplier-cost exposure or real-product Offer schema.
- Real cart/checkout/payment readiness rejects snapshot prices even with fresh stock. Permanent sandbox remains 2 × 10 NOK and no real delivery.
- Inbox command reports an intentional pause; manual bundle import rejects; admin retains raw stale health.
- NB/RU tyre/wheel listing/detail and admin checked at 1440, 390 and 320 px. Direct requests only in isolated local fixtures; live smoke read-only.
- Release-local mode change, fresh gzip-valid backup, atomic activation/rollback, no migrations/seeds/imports/pruning. Check for in-flight old import commands; this mode does not cancel already-started work.

Owner directly authorised this staging activation. Final PM acceptance and production/provider-delivery acceptance remain separate.

## Historical Issue #19 review (superseded as current task)

Review only the final Issue #19 handoff branch against accepted base `f25a005ac0277ce0ef03c303a2e7c9a997a83a48`. The exact candidate head and PR are published in Issue #19 when ready.

- PR: [#72 — Finish Issue #19 catalogue handoff](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/72).
- Exact review head: recorded in PR #72 and the latest Issue #19 engineer update; reviewers must verify that immutable SHA before acceptance.

## Required invariants

- Published active real products remain browseable and detail-visible when stock is missing, stale or zero.
- Visibility is independent from purchasability.
- Add-to-cart, checkout and payment require a valid selling price plus fresh, sufficient positive stock; direct requests remain fail-closed.
- Missing price is explicit and never represented as zero, purchase price or supplier cost.
- An unavailable real product has a nearby localized contact action that opens `/kontakt` or `/ru/kontakt` and does not imply that an order was placed.
- Existing safe prices remain visible even when stock is stale; fresh ordinary products retain their purchase controls.
- The staging-only demo remains fixed at quantity `2`, unit `10 NOK`, total `20 NOK`, includes the no-real-delivery copy and stays isolated from production and commercial NDI ordering.

## Focused evidence expected

- PHP rendering assertions for tyre and wheel, NB/RU, valid price and missing price.
- Issue #19 Playwright at 1440, 390 and 320 px for NB/RU: CTA visible and keyboard-operable, target opens, price placeholder visible when required, no horizontal overflow.
- Existing direct cart rejection remains green; no new external payment or supplier call is needed.
- One normal CI run on the final exact head. Record only checks actually run for this candidate.

## Release and handoff

The active application before this closeout is the accepted `f25a005ac0277ce0ef03c303a2e7c9a997a83a48` release; its accepted runtime evidence is linked from `STATUS.md`. A narrow code/assets-only staging preview is authorised after focused checks, with a fresh server read-back, valid backup and retained rollback. Final merge remains the PM's exact-head decision.

Once the final main/application identity, two real product URLs, permanent demo URL, rollback, clean working-copy path and absence of competing application work are confirmed in Issue #19, the PM may post `CATALOG HANDOFF READY`. The designer then starts one branch from that final accepted `main`; old PRs and branches are not inputs.
