# EasyTire permanent decisions

Updated: 2026-09-15

Only decisions that must survive closed Issues belong here. Implementation detail stays in code/As-Built docs; discussion stays in Issues and PRs.

## GOV-000 — Simplified implementation and release cycle

[Owner/PM decision #44, comment 5670860452](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5670860452) supersedes conflicting procedural gates below and in historical tasks. Implement → focused checks and one concise result → PM review → merge and whole staging-release permission in one decision → deploy/verify/handover. Routine engineering steps need no repeated owner confirmation. Data, access, price, booking and integration boundaries remain in force. See [WORKFLOW.md](WORKFLOW.md).

## GOV-001 — GitHub is the canonical engineering gateway

Design-only preview exception to the general release sequence above: [PM #44, comment 5673643971](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5673643971) allows published unmerged design SHAs on existing staging after build/focused checks, with direct visual review and no migrations, seeds, pruning or integration changes. Previous and accepted releases are retained; final acceptance remains separate.

- `origin/main` is the accepted code baseline.
- One bounded task uses one Issue, one branch and one PR.
- The server `staging` remote is deployment-only and cannot become a competing source of truth.

## GOV-002 — Truth is domain-specific

- Requirements: permanent decisions plus the active authorised Issue.
- Implementation: accepted `main` plus tests/As-Built docs.
- Runtime: fresh environment read-back plus `STATUS.md`.
- Existing code never overrides a later explicit customer decision merely because it was merged earlier.

## GOV-003 — PM and engineer are separate gates

- ChatGPT Pro/PM defines and accepts scope.
- Mister Red/Codex is chief engineer and does not approve its own PR.
- PM review is bound to an exact head SHA; a later commit invalidates it.

## GOV-004 — Merge is not runtime authorisation

- Payment, NDI C1, capture/refund, import, messaging and production changes require an explicit runtime decision.
- CI is hermetic and may not perform real external side effects.

## GOV-005 — Customer messages become scope through an Issue

- Telegram, calls, PDFs, spreadsheets, audio/video and supplied files remain evidence.
- PM records the accepted interpretation, exact source and superseded decision in the Issue.
- Uncertain interpretation is a blocker, not permission to invent.

## GOV-006 — Proportionate evidence, not archive duplication

- CI logs, tests and small redacted evidence are preferred to handoff ZIPs.
- Large/private source materials remain outside Git; tracked companions include source path/date and SHA-256 where needed.
- Full E2E is the default for this customer-facing site; visual changes also require named viewport screenshots.

## GOV-007 — Temporary combined project-lead mode

- Owner decision on 2026-08-24: until a separate project manager returns, Mister Red/Codex acts as both project lead and chief engineer under the former workflow.
- Issue, branch, PR, CI, exact-SHA evidence and explicit runtime authorization remain mandatory.
- During this temporary mode Mister Red may record the exact-SHA verdict and execute an owner-authorised staging gate; it still may not infer production authorization or broaden external side effects.
- When the owner restores a separate PM/PRO reviewer, GOV-003 becomes the active review separation again without changing the engineering gates.

## GOV-008 — Audit before remediation; Notion postponed

- Owner decision on 2026-08-29: a separate PM is restored, so GOV-003 is active and Mister Red/Codex acts as chief engineer.
- Website corrections begin only after Issue #10 has traced current customer sources to accepted code, tests and staging; historical hypotheses are not authorised correction scope.
- Notion work is postponed and excluded from the current website audit/remediation initiative until a new explicit owner decision starts a separate bounded task.

## GOV-009 — Routine engineering publication is pre-authorised

- The standing decision in [Issue #44 comment 5654897537](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5654897537) authorises required Issue/PR reporting, cross-links, named branches/PRs, pushes and proportionate local/CI checks inside an already authorised task without repeated owner acknowledgements.
- It never widens scope or implicitly authorises merge, deployment, customer-data mutation, production operation or an external integration side effect.

## COM-001 — Dintero capture follows physical readiness

The current contract is [`DINTERO_PAYMENT_LIFECYCLE.md`](../docs/architecture/DINTERO_PAYMENT_LIFECYCLE.md): authorization first, guarded NDI C1, then operator-confirmed goods readiness and capture. Any older document wording that says “capture immediately after NDI accepted” is superseded.

## COM-002 — NDI sandbox and commercial NDI are isolated

The staging full-cycle fixture is limited to PartyID `1220000`, article `4765205551649102`, quantity `2`. Sandbox enablement never enables commercial NDI ordering and is forbidden in production.

## COM-003 — Catalogue visibility is separate from purchase eligibility

- A published active real tyre or wheel remains browseable and keeps its permanent detail URL when stock is missing, stale or zero.
- Visibility never grants permission to buy. Cart, checkout and payment require a valid selling price and fresh, sufficient positive stock, enforced server-side even for direct requests or an old browser tab.
- Missing price is shown explicitly; zero, supplier cost and an invented price are forbidden.
- The staging-only demo is independent from live NDI stock freshness, but its fixed test contract never enables production exposure, a real payment, a commercial NDI order or delivery.
- A new authoritative NDI delivery remains an external prerequisite for making stale real products purchasable; it is not a prerequisite for browsing or design work.

Source: [Issue #19 final handoff scope, comment 5689353790](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5689353790).

## COM-004 — Explicit non-production historical-price preview

Owner decision 2026-09-16, [Issue #75](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/75): allow `NDI_CATALOG_MODE=snapshot` on staging/local/testing only. Saved dated source prices may be calculated through the existing active markup rule for clearly labelled test display. Original dates and raw stale health remain intact; automated inbox processing pauses and manual bundle import is rejected. Real commerce receives no eligible price, regardless of stock/date; the independently guarded sandbox contract is unchanged.

This is a display-only extension to COM-003, not permission to sell stale goods or retrospectively claim a working supplier feed. `live` restores ordinary freshness enforcement and import commands but is not sufficient for go-live: actual provider delivery/schema/cadence and unchanged-content-versus-replay acceptance remain required. See [runbook](../docs/operations/NDI_DELIVERY_CONNECTION.md).

Owner specifically authorised the no-migration snapshot staging activation on top of the current unmerged designer preview, after checks and a fresh backup. This bounded exception neither merges/accepts #74 nor authorises production, DNS, provider messages or commercial calls.
