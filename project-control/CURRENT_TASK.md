# Current task

- Umbrella: [#9 — Audit website against customer sources and remediate confirmed gaps](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/9)
- Issue: [#33 — Consolidate storefront geometry and presentation ownership](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/33)
- State: `engineer-complete-ci-pending`
- Branch: `codex/issue-33-storefront-design-system-consolidation`
- PR: [#40 — Consolidate storefront design system](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/40) (Draft)
- Exact base: `87509a644022a4df232c118d9bf4bab32f2c3683`
- Exact locally tested implementation head: `e33195eaa6a052fd7e308779402e7ad87c51dfda`
- Scope: remove the universal radius override and migrate repeated static storefront inline presentation into explicit token/component ownership without changing the accepted appearance or behaviour from Issues #28–#32.
- Verification: before/after ownership inventories and six-viewport browser proof are recorded under `evidence/issue-33/`; production build, Pint, 421-test Laravel regression, 197-case buyer/admin E2E, Issue #33 proof and the accepted Issue #26 audit are green locally. Pull-request CI and PM exact-head review remain pending.
- Runtime authorisation: none; local/CI synthetic and read-only visual comparison only, no deployment or external side effects.

The Issue is the full task. This file is only a pointer.
