# EasyTire website conformity audit

Audit date: 2026-08-29

Issue: [#10](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/10)

Base implementation: `4f95712be4bf2238219b51533301823595c306a0`

Staging revision checked: `01a931d432b32a5a0633d1b508256b225387d429`

Change boundary: documentation and redacted evidence only; no application/runtime behaviour changed.

## Executive result

The accepted website implementation is substantially conformant with the reconciled customer baseline. The audit contains 114 atomic requirements:

| Classification | Rows | Meaning |
|---|---:|---|
| `MATCH` | 94 | Implemented and supported by code/test/runtime evidence. |
| `MISMATCH` | 4 | Three unique actionable findings; `FND-01` affects two atomic requirements. |
| `EXTERNAL_BLOCKER` | 10 | Depends on supplier/provider/customer rights, credentials, data or production approval. |
| `DECISION_REQUIRED` | 4 | Product/commercial choice was discussed but never finally approved. |
| `TZ_STALE` | 2 | A previous management summary is outdated and is corrected by primary evidence/owner decision. |

This result does not support a broad redesign or rewrite. It supports one operational P1 repair, two bounded P2 repairs and separate decision/external-dependency follow-ups.

## Actionable findings

### FND-01 — P1 — supplier feed freshness prevents the real catalogue from being public

**Evidence:** the local normalized database contains the NDI catalogue, but staging health reports that the last successful dataset is far beyond the configured freshness window. The storefront correctly fails closed: current real NDI stock is hidden and only the explicitly isolated sandbox SKU is public. The supplier inbox/import health requirement is therefore not met.

**Impact:** buyers cannot browse the intended real tyre/wheel range. Image coverage also cannot be judged from the public catalogue while the feed is stale.

**Reproduction:** open staging `/dekk` and `/felger`; compare public results with the redacted `easytire:health` supplier-freshness state and latest import state.

**Bounded remediation:** investigate the NDI inbox/SFTP arrival, scheduler and import chain; restore one successful current dataset; keep the freshness safety gate; confirm both tyre and wheel publication plus image import; add/confirm an operational alert. Do not publish stale stock as a shortcut.

**Acceptance:** a current successful feed is inside the configured window; health is green; representative tyres and wheels are public; stale-fixture tests still prove fail-closed behaviour.

### FND-02 — P2 — battery request misses two required customer fields

**Evidence:** the SEO workbook requires problem description, registration number, desired date, name and phone. The implemented request path requires the problem but currently treats registration number and preferred date as nullable.

**Impact:** staff may receive a lead that cannot be scheduled or tied to the correct vehicle without manual follow-up.

**Bounded remediation:** require both fields only for the battery service request; provide Norwegian and hidden-Russian validation; preserve the generic form contract for services whose source does not require them.

**Acceptance:** UI and server reject missing registration/date for battery requests; valid requests persist; localized feature and Playwright coverage passes.

### FND-03 — P2 — staging disk is below the configured warning threshold

**Evidence:** the redacted staging health check reports disk warning while database, cache, queue, scheduler and worker checks are healthy.

**Impact:** imports, releases, logs or backups can fail if free space continues to fall.

**Bounded remediation:** inventory only safe cleanup candidates, retain rollback releases/logs/backups per runbook, reclaim space and re-run the health check. No broad recursive deletion is authorized by this finding.

**Acceptance:** disk health is above the configured threshold and deployment/import smoke remains green.

## Decisions still required

| ID | Decision | Why it is not a defect |
|---|---|---|
| `DEC-01` | Approve values, dates and terms for two-car and early-October promotions. | Customer supplied the promotion ideas, not final commercial terms. |
| `DEC-02` | Choose hide-or-label policy for older DOT/production-year stock. | The concern is explicit; a final commercial threshold/action is not. |
| `DEC-03` | Choose two-van allocation: geographic areas or fill one then the second. | Two-van capacity is confirmed; the customer explicitly allowed the team to choose the algorithm. |

## External prerequisites

- `EXT-01`: customer-approved B2B partner logos and usage rights.
- `EXT-02`: licensed PCD/DIA/full-kit wheel fitment matrix.
- `EXT-03`: official TPMS applicability/product/programming rules.
- `EXT-04`: official NDI/customer product image coverage.
- `EXT-05`: written production pricing formula, including fee/VAT/source price/markup/rounding.
- `EXT-06`: production Dintero KYC/account/client/profile/signature approval.
- `EXT-07`: production outbound email delivery configuration.
- `EXT-08`: NDI returned reference and provider fulfilment/status capability.
- `EXT-09`: Telegram target credentials and explicit activation decision.

## Corrections to earlier management material

- The SEO workbook is accessible and was read completely; its 11 routes were mapped and verified. The earlier `blocked access` statement is stale (`STALE-01`).
- All three Telegram voice messages were independently transcribed locally. The important confirmed outcome is two mobile vans with an intentionally open allocation algorithm.
- Notion was originally described by the customer as later/separate work and was explicitly postponed by the owner on 2026-08-29. It is outside this website initiative (`STALE-02`).
- PM/ChatGPT Pro drafts are secondary proposals. They cannot override later customer wording, the correction PDF or accepted sharp storefront decisions.

## Implementation and source verification

- Complete primary-source inventory and precedence: [`WEBSITE_SOURCE_REGISTER.md`](WEBSITE_SOURCE_REGISTER.md).
- Atomic source → implementation → verification matrix: [`WEBSITE_REQUIREMENTS_TRACEABILITY.csv`](WEBSITE_REQUIREMENTS_TRACEABILITY.csv).
- Consolidated current contract: [`WEBSITE_REQUIREMENTS_BASELINE.md`](WEBSITE_REQUIREMENTS_BASELINE.md).
- Redacted visual evidence: [`../../evidence/issue-10/INDEX.md`](../../evidence/issue-10/INDEX.md).

The audit checked routes, controllers/services, migrations/models, admin forms, localization, CSS/view composition, NDI/Dintero/Statens boundaries, legal content, environment feature flags and relevant automated contracts. Secrets and private customer payloads were not copied into Git.

## Test and staging baseline

- Laravel full suite: **402 passed**, **3,066 assertions**, **1 skipped**.
- Playwright local suite: **124 passed**, **5 intentional skips**, **0 failed**, including buyer/admin, desktop, 390px, 320px and Russian QA coverage.
- Production frontend build: passed; 61 modules transformed.
- Pint format check: passed.
- All 11 SEO routes returned 200 with non-empty H1/title/meta and exact canonical paths.
- Public routes checked at 1440×900, 390×844 and 320×800: no page errors, console errors or horizontal overflow.
- Hidden `/ru` returned `lang=ru` and `noindex,nofollow`.
- The Google map loaded immediately after scroll/wait and showed the confirmed Ringvegen destination.
- Authenticated custom-admin GET smoke passed at all three viewports without horizontal overflow. No customer/admin PII screenshot was committed.
- Staging database, cache, queue, scheduler and worker health were green; NDI freshness and disk threshold produced the two operational warnings documented above.

## Verification limitations

- The in-app browser connector failed to initialize its local assets. The same read-only checks were completed with the repository Playwright stack; this is a tooling limitation, not a website failure.
- Production was not accessed and no real-money checkout, commercial NDI C1 order, supplier fulfilment status, outbound email, Telegram or Notion activation was attempted.
- Public product-image coverage cannot be fairly measured until the current NDI feed is restored. The implemented missing-image behaviour was verified and remains honest.
- Customer videos were sampled visually as marketing/workshop material; they contain no governing textual specification that supersedes the written/voice sources.

## Proposed follow-up Issue boundaries

1. **Restore NDI feed freshness and catalogue publication (`FND-01`)** — operations/import only; no storefront redesign.
2. **Complete battery request contract (`FND-02`)** — one service form, validation, localization and tests.
3. **Restore staging disk headroom (`FND-03`)** — safe operations cleanup with before/after health evidence.
4. **Commercial decisions (`DEC-01`–`DEC-03`)** — owner/PM/customer decision Issue; no implementation until resolved.
5. **External prerequisites (`EXT-01`–`EXT-09`)** — separate integration/content gates with named owners and evidence.

Per protocol, no remediation should begin until the PM reviews this exact audit head SHA and turns confirmed findings into bounded Issues.
