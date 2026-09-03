# Current task

- Umbrella: [#9 — Audit website against customer sources and remediate confirmed gaps](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9)
- Issue: [#32 — Polish catalogue, product and cart journeys](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/32)
- State: `ci-pending`
- Branch: `codex/issue-32-catalog-product-cart-polish`
- PR: [#39 — Polish catalogue product and cart journeys](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/39) (Draft)
- Exact base: `9d49ac82ced177d5310a909ddb7e82fff9c3678a`
- Exact green implementation head: `833db99df9c903ca68ab8af0f219de0dd0a8ead1`
- Scope: mobile deferred-Apply filter behaviour, natural desktop filter flow, customer-facing category navigation, readable product buy box and intentional empty-cart composition; preserve accepted #28–#31 behaviour and every NDI/commerce boundary.
- Verification: local exact implementation passed — Laravel 418 passed / 1 skipped (3887 assertions), buyer/admin Playwright 156 passed / 41 viewport skips, accepted Issue #26 audit runnable matrix 43 passed, Issue #32 evidence capture passed, build/Pint/project-control passed; GitHub Actions and reachable-history secret scan pending on PR #39.
- Runtime authorisation: none; local/CI synthetic only, no deployment, NDI, Dintero, email, Notion, Telegram or other external side effect.

The Issue is the full task. This file is only a pointer.
