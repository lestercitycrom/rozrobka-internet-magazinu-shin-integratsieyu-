# EasyTire status

Updated: 2026-08-22  
Accepted baseline: `c477e4a7869d0bbedbe02d7e18203433d341c3a2`  
Baseline tag: `github-bootstrap-baseline-2026-08-22`

## Environments

| Environment | State | Canonical evidence |
|---|---|---|
| GitHub `main` | Accepted baseline published | `origin/main` at baseline SHA |
| Staging | Online on accepted baseline | <https://staging-easytire.bethub.fun> |
| Production | Not authorised by this repository transition | Runtime decision required |

## Runtime gates

| Gate | State |
|---|---|
| Dintero | Test environment active on staging; customer completed a sandbox checkout. Production account/capture policy acceptance remains external. |
| NDI catalogue | Normalised catalogue/import boundary implemented. Freshness and feed operations remain monitored separately. |
| NDI sandbox order | Restricted test fixture/profile only: PartyID `1220000`, article `4765205551649102`, quantity `2`. |
| Commercial NDI C1 | Disabled unless a separately authorised production gate enables it. |
| Notion / Telegram | Code/outbox boundary exists; disabled pending approved schema, credentials and PII/idempotency decision. |
| Statens lookup | Implemented where environment key enables it; wheel/full-kit compatibility remains blocked on licensed PCD/DIA fitment data. |
| RU storefront | Hidden QA route, `noindex`; not a public language selector. |

## Current quality baseline

- Linux release regression: 396 tests, 3008 assertions.
- Staging product page/read-back confirmed for commit `c477e4a`.
- Reachable Git history scan before first GitHub push: 58 commits, no secret-pattern matches.
- `docs/access.md`, `.env`, private customer materials and runtime credentials are excluded from Git.

## Current work

- Active task: [Issue #1](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/1)
- Review: see [`CURRENT_REVIEW.md`](CURRENT_REVIEW.md)
- Current requirements: [`docs/management/CURRENT_TZ_2026-08-22.md`](../docs/management/CURRENT_TZ_2026-08-22.md)

This file is a short runtime snapshot. Detailed history belongs to merged Issues, PRs and the linked reports.
