# Current task

- Umbrella: [#9 — Audit website against customer sources and remediate confirmed gaps](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9)
- Issue: [#31 — Rework services and request-page composition](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/31)
- State: `pm-review`
- Branch: `codex/issue-31-services-request-composition`
- PR: [#38 — Rework services and request composition](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/38)
- Exact base: `d69d8d2a70d492c039ae2e76be67938bda274349`
- Scope: all 13 service destinations, their approved-media/fallback contract, service-detail desktop composition, generic request-page composition and service/battery CTA hierarchy; preserve accepted #28–#30 behaviour and all protected business contracts.
- Verification: deterministic 13-service inventory, implementation and root `evidence/issue-31/` are complete; local gates passed (418 Laravel tests, 1 skipped; 145 Playwright E2E tests, 19 skipped; 25 applicable interaction-audit tests, 29 viewport skips; Pint, production build and project-control validation). The immutable head SHA and GitHub Actions result are recorded in PR #38 and the final `ENGINEER UPDATE`.
- Runtime authorisation: none; local/CI synthetic only, no deployment, NDI, Dintero, email, Notion, Telegram or other external side effect.

The Issue is the full task. This file is only a pointer.
