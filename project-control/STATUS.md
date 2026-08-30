# EasyTire status

Updated: 2026-08-30
Accepted release: `01a931d432b32a5a0633d1b508256b225387d429`

## Environments

| Environment | State | Canonical evidence |
|---|---|---|
| GitHub `main` | Accepted website conformity audit merged | `05497aeb89d751a8dcf2f7a6d7c914a4cd3c8736` / PR #17 |
| Staging | Accepted release active and verified | <https://staging-easytire.bethub.fun>, server `REVISION` read-back |
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

- GitHub Actions [run 33245827285](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/33245827285): exact accepted audit head passed application quality and reachable-history secret scan.
- Server release regression: 403 tests, 3069 assertions; backup, additive migrations, seeders and atomic activation passed.
- Local release E2E: 124 passed, 5 intentional skips across buyer/admin desktop, mobile, 320px and RU QA projects.
- Live staging Playwright: product → cart → checkout without submission; merchant identity, VAT, consents, legal links, PDF and RU `noindex` passed.
- Server health read-back: database, queue, cache, scheduler, worker and disk healthy. NDI feed freshness remains the external warning tracked as `FND-01`.
- `docs/access.md`, `.env`, private customer materials and runtime credentials are excluded from Git.

## Current work

- Active initiative: [Issue #9](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9), audit-first website conformity and later bounded remediation.
- Accepted audit: [Issue #10](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/10) / PR #17, merged as `05497aeb89d751a8dcf2f7a6d7c914a4cd3c8736`.
- Completed remediation: [Issue #18](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/18) / PR #21, merged as `de3854d9289618d443ded5c50176e6dc43d875a6`; staging disk health is green.
- External blocker: [Issue #19](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19) is waiting for a current complete NDI supplier dataset; PR #22 remains Draft and no NDI runtime mutation is authorised.
- Ready for PM review: [Issue #20](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/20) / PR #23, bounded battery-only request contract on `codex/issue-20-battery-request-contract`; local/CI synthetic verification only and no staging deployment.
- Notion is postponed and excluded from the current website remediation initiative.
- Reconciled website baseline: [`docs/management/WEBSITE_REQUIREMENTS_BASELINE.md`](../docs/management/WEBSITE_REQUIREMENTS_BASELINE.md).
- Corrected audit result: 119 atomic requirements — 99 match, 4 mismatch rows (3 unique findings), 10 external blockers, 4 decisions required and 2 stale-TZ corrections.

This file is a short runtime snapshot. Detailed history belongs to merged Issues, PRs and the linked reports.
