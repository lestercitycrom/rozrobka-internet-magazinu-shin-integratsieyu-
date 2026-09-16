# Current task

Lifecycle: this pointer applies only while the final handoff in Issue #19 remains open. After the PM posts `CATALOG HANDOFF READY`, no further catalogue implementation is implied by this file.

- Issue: [#19 — catalogue availability recovery](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19).
- Authoritative scope: [final handoff comment 5689353790](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19#issuecomment-5689353790).
- Accepted base: `origin/main` at `f25a005ac0277ce0ef03c303a2e7c9a997a83a48`.
- Branch: `codex/issue-19-final-handoff`.
- PR: the single PR opened from this branch with `Refs #19`; its exact head is recorded in the PR and Issue, not predicted here.

## Bounded implementation

1. On an unavailable real tyre or wheel detail, show NB `Spør om pris og tilgjengelighet` / RU `Уточнить цену и наличие` and link to the existing localized contact page.
2. When no safe selling price exists, show the existing localized `storefront.price_unavailable` copy instead of an empty price area, zero or supplier cost.
3. Keep the purchase form absent and preserve direct cart/checkout/payment rejection whenever price or stock guards fail.
4. Verify the affected states in NB/RU at 1440, 390 and 320 px, including the working contact target and no horizontal overflow.
5. Confirm that a fresh ordinary product still has the normal purchase path and that the staging demo remains exactly `2 × 10 NOK`, explicitly without real delivery.

## Explicit non-scope

- No migration, seed, supplier import, stock timestamp change, NDI inquiry/C1/order, payment, capture, notification, CRM endpoint, production or DNS action.
- Do not reopen or cherry-pick old PRs #22, #70 or #71. PRs #70 and #71 are accepted history.
- Do not weaken freshness, price, cart, checkout or payment guards to make stale products purchasable.

The external dependency remains unchanged: a genuinely new authoritative NDI delivery is required before real stale products can become purchasable. It does not block catalogue browsing, the permanent demo or the design handoff.
