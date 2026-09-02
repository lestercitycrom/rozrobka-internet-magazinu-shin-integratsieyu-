# EasyTire status

Updated: 2026-09-02
Accepted release: `ee512d723e78a65a89bfdee4268c3a779f632b33`
Retained rollback: `01a931d432b32a5a0633d1b508256b225387d429`

## Environments

| Environment | State | Canonical evidence |
|---|---|---|
| GitHub `main` | Shared storefront interaction shell accepted and merged; not deployed | `0678fe39964292648c52ab97119b9977acbeaba2` / PR #36 |
| Staging | Same exact release deployed and runtime-accepted | <https://staging-easytire.bethub.fun>, server `REVISION` read-back |
| Production | Not authorised | Separate production review and runtime decision required |

## Runtime gates

| Gate | State |
|---|---|
| Dintero | Merchant-review package active on staging with manual capture/void/refund/reconcile and legal evidence; sandbox only. Production account/KYC and acceptance remain external. |
| NDI catalogue | Normalised catalogue/import boundary implemented, but the staging feed is currently beyond its freshness window. The correct fail-closed gate hides real NDI stock until a current import succeeds. |
| NDI sandbox order | Restricted test fixture/profile only: PartyID `1220000`, article `4765205551649102`, quantity `2`. |
| Commercial NDI C1 | Disabled unless a separately authorised production gate enables it. |
| Notion / Telegram | Code/outbox boundary exists and remains disabled. Notion work is postponed by the owner; no audit or runtime activation is authorised in the current website initiative. |
| Statens lookup | Implemented where environment key enables it; wheel/full-kit compatibility remains blocked on licensed PCD/DIA fitment data. |
| RU storefront | Hidden QA route, `noindex`; not a public language selector. |

## Current quality baseline

- GitHub-main baseline: exact accepted PR #36 head passed GitHub Actions [run 33489069245](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/33489069245): Laravel (417 tests, 3813 assertions), Playwright (141 passed, 15 project-defined skips), frontend build, Pint, project-control validation and reachable-history secret scan.
- Staging deployment/read-back: server `REVISION` matched `ee512d723e78a65a89bfdee4268c3a779f632b33`; migrations reported `Nothing to migrate`; atomic activation completed with rollback `01a931d432b32a5a0633d1b508256b225387d429` retained; database, cache, queue, scheduler, worker, failed jobs and disk were healthy. The isolated NDI feed-freshness warning remains tracked as `FND-01`.
- Live staging validation: Bokmål and hidden RU battery validation passed without a persistent request or external side effect; representative non-battery behaviour remained unchanged.
- `docs/access.md`, `.env`, private customer materials and runtime credentials are excluded from Git.

## Current work

- Active initiative: [Issue #9](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9), audit-first website conformity and later bounded remediation.
- Accepted audit: [Issue #10](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/10) / PR #17, merged as `05497aeb89d751a8dcf2f7a6d7c914a4cd3c8736`.
- Completed remediation: [Issue #18](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/18) / PR #21, merged as `de3854d9289618d443ded5c50176e6dc43d875a6`; staging disk health is green.
- Completed remediation: [Issue #20](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/20) / PR #23, merged, deployed and runtime-accepted as `ee512d723e78a65a89bfdee4268c3a779f632b33`.
- Remaining confirmed remediation: [Issue #19](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19) is `blocked-external` pending a current complete NDI `stock` / `tires` / `wheels` dataset. PR #22 remains Draft; reviewed diagnostic head `d9815b99ee1f9dff4b10479c22f32a533e65591f`.
- Issue #28 / PR #35 is merged on GitHub as `07ce99a2d09afdfe521f6150486aa98c799cef97`; this does not change staging or any integration gate.
- Issue #29 / PR #36 is accepted and merged on GitHub as `0678fe39964292648c52ab97119b9977acbeaba2`; this does not change staging or any integration gate.
- Active internal remediation: [Issue #30](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/30), home hero/finder focal composition and the four-step process strip. Runtime authorization remains none.
- Notion is postponed and excluded from the current website remediation initiative.
- Reconciled website baseline: [`docs/management/WEBSITE_REQUIREMENTS_BASELINE.md`](../docs/management/WEBSITE_REQUIREMENTS_BASELINE.md).
- Corrected audit result: 119 atomic requirements — 99 match, 4 mismatch rows (3 unique findings), 10 external blockers, 4 decisions required and 2 stale-TZ corrections.

This file is a short runtime snapshot. Detailed history belongs to merged Issues, PRs and the linked reports.
