# Current review

- Issue: [#19 — catalogue availability recovery](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/19).
- PR: [#70 — Fix Issue #19 catalogue availability recovery](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/70).
- Branch: `codex/issue-19-catalog-availability`.
- Exact accepted base: `origin/main` at `47dfdee2bb9538ba70742b0c88cf1edd4a85707c`.
- Application candidate: `aeb48a8049138c5dd0786f2b93fd3b696c9f37bd`. The review branch additionally contains project-control and verification/CI-harness corrections; the exact final head is reported in PR #70. No deployment has been performed.
- Active staging application: `980fdc117d27a7bcf5d0d0cd950e31e74cee1497`; immediate rollback `33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b`.
- Browse contract: published active real NDI products remain visible with localized missing, stale, zero, local or remote availability states. The in-stock filter remains fresh-positive only.
- Commerce contract: missing, stale, zero or insufficient stock cannot enter a valid cart or proceed through checkout/payment. Fresh secondary warehouse data cannot mask stale authoritative Norway or contributing physical rows.
- Environment contract: production excludes every test fixture. The only approved sandbox order is immutable SKU `NDI-TEST-4765205551649102`, quantity `2`, unit `10 NOK`, total `20 NOK`, and it is blocked outside Dintero test.
- Operations contract: missing `NDI_INBOX_PATH` fails explicitly; supplier health uses only successful live non-test stock imports, cannot outlive storefront stock freshness and reports redacted inbox configuration/readiness/receipt state without paths or filenames.
- Import provenance contract: the retained stored-artifact observation timestamp, initially derived from source-file filesystem modification time (mtime), not processing time, governs stock freshness. Inbox duplicate detection spans parser versions; retained artifact time and older-observation guards prevent same-content manual replay or a touched already-stored duplicate from renewing freshness. Future observations are rejected before mutation and older observations cannot replace newer current stock.
- Final local verification is green: full Laravel `589 passed / 5,550 assertions / 1 expected Windows symlink skip`; standard Playwright `251 passed / 132 expected skips / 0 failures`; dedicated Issue #19 Playwright `11 passed / 4 expected skips`; supplemental map/content `56 passed` and sequential admin chain `16 passed`; Pint and production asset build are clean. Independent code review found no blocking or material defect.
- Review gate: CI on the final review head and exact-head review are pending. Merge and deployment remain separate later steps.
- Runtime gate: future inbox configuration repair is planned. A new external NDI delivery remains the blocker to fresh purchasable real inventory, but not to catalogue browsing/demo after deployment.
- Conservative fail-closed limitation: byte-identical supplier content cannot prove a new delivery until a trusted delivery identifier/sequence is available and implemented; a full bundle uses its oldest constituent mtime. Either condition may keep purchasing blocked, but cannot make stale inventory sellable.

Detailed diagnosis, candidate behaviour, verification limits and redacted runtime facts are recorded in `evidence/issue-19/catalog-recovery.md`.
