# EasyTire status

Updated: 2026-08-29
Accepted release: `01a931d432b32a5a0633d1b508256b225387d429`

## Environments

| Environment | State | Canonical evidence |
|---|---|---|
| GitHub `main` | Dintero merchant-review release plus project-control documentation | `4f95712be4bf2238219b51533301823595c306a0` at audit start |
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

- GitHub Actions [run 32705217997](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/32705217997): application quality and reachable-history secret scan passed.
- Server release regression: 403 tests, 3069 assertions; backup, additive migrations, seeders and atomic activation passed.
- Local release E2E: 124 passed, 5 intentional skips across buyer/admin desktop, mobile, 320px and RU QA projects.
- Live staging Playwright: product → cart → checkout without submission; merchant identity, VAT, consents, legal links, PDF and RU `noindex` passed.
- Server health read-back: database, queue, cache, scheduler and worker healthy. NDI feed freshness and disk headroom are warnings tracked as `FND-01` and `FND-03` in the current audit.
- `docs/access.md`, `.env`, private customer materials and runtime credentials are excluded from Git.

## Current work

- Active initiative: [Issue #9](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9), audit-first website conformity and later bounded remediation.
- Active task: [Issue #10](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/10), documentation-only source-to-implementation audit on `codex/issue-10-source-conformity-audit`.
- GitHub `main` at audit start: `4f95712be4bf2238219b51533301823595c306a0`.
- Notion is postponed and excluded from the current website remediation initiative.
- Reconciled website baseline: [`docs/management/WEBSITE_REQUIREMENTS_BASELINE.md`](../docs/management/WEBSITE_REQUIREMENTS_BASELINE.md).
- Audit result: 114 atomic requirements — 94 match, 4 mismatch rows (3 unique findings), 10 external blockers, 4 decisions required and 2 stale-TZ corrections.

This file is a short runtime snapshot. Detailed history belongs to merged Issues, PRs and the linked reports.
