# EasyTire permanent decisions

Updated: 2026-08-24

Only decisions that must survive closed Issues belong here. Implementation detail stays in code/As-Built docs; discussion stays in Issues and PRs.

## GOV-001 — GitHub is the canonical engineering gateway

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

## COM-001 — Dintero capture follows physical readiness

The current contract is [`DINTERO_PAYMENT_LIFECYCLE.md`](../docs/architecture/DINTERO_PAYMENT_LIFECYCLE.md): authorization first, guarded NDI C1, then operator-confirmed goods readiness and capture. Any older document wording that says “capture immediately after NDI accepted” is superseded.

## COM-002 — NDI sandbox and commercial NDI are isolated

The staging full-cycle fixture is limited to PartyID `1220000`, article `4765205551649102`, quantity `2`. Sandbox enablement never enables commercial NDI ordering and is forbidden in production.
