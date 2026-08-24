# EasyTire status

Updated: 2026-08-24
Accepted release: `01a931d432b32a5a0633d1b508256b225387d429`

## Environments

| Environment | State | Canonical evidence |
|---|---|---|
| GitHub `main` | Dintero merchant-review release merged | [PR #5](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/5) at accepted release SHA |
| Staging | Accepted release active and verified | <https://staging-easytire.bethub.fun>, server `REVISION` read-back |
| Production | Not authorised | Separate production review and runtime decision required |

## Runtime gates

| Gate | State |
|---|---|
| Dintero | Merchant-review package active on staging with manual capture/void/refund/reconcile and legal evidence; sandbox only. Production account/KYC and acceptance remain external. |
| NDI catalogue | Normalised catalogue/import boundary implemented. Freshness and feed operations remain monitored separately. |
| NDI sandbox order | Restricted test fixture/profile only: PartyID `1220000`, article `4765205551649102`, quantity `2`. |
| Commercial NDI C1 | Disabled unless a separately authorised production gate enables it. |
| Notion / Telegram | Code/outbox boundary exists; disabled pending approved schema, credentials and PII/idempotency decision. |
| Statens lookup | Implemented where environment key enables it; wheel/full-kit compatibility remains blocked on licensed PCD/DIA fitment data. |
| RU storefront | Hidden QA route, `noindex`; not a public language selector. |

## Current quality baseline

- GitHub Actions [run 32705217997](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/32705217997): application quality and reachable-history secret scan passed.
- Server release regression: 403 tests, 3069 assertions; backup, additive migrations, seeders and atomic activation passed.
- Local release E2E: 124 passed, 5 intentional skips across buyer/admin desktop, mobile, 320px and RU QA projects.
- Live staging Playwright: product → cart → checkout without submission; merchant identity, VAT, consents, legal links, PDF and RU `noindex` passed.
- Server health: database, queue, cache, scheduler, worker and disk healthy; failed jobs 0.
- `docs/access.md`, `.env`, private customer materials and runtime credentials are excluded from Git.

## Current work

- Active task: [Issue #6](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/6), documentation-only release record.
- Completed Dintero task: [Issue #4](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/4) / [PR #5](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/5).
- Current requirements: [`docs/management/CURRENT_TZ_2026-08-22.md`](../docs/management/CURRENT_TZ_2026-08-22.md)

This file is a short runtime snapshot. Detailed history belongs to merged Issues, PRs and the linked reports.
