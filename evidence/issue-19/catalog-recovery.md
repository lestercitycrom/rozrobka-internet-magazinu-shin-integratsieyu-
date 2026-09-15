# Issue #19 — catalogue availability recovery

Recorded: 2026-09-15

This record separates catalogue browsing, commerce safety and supplier ingestion. It contains aggregate/redacted evidence only: no server path, supplier filename, credential, payload content or personal data is recorded.

## Version and runtime boundary

| Item | Exact state |
|---|---|
| Accepted base | `origin/main` at `47dfdee2bb9538ba70742b0c88cf1edd4a85707c` |
| Issue branch | `codex/issue-19-catalog-availability` |
| Pull request | [#70 — Fix Issue #19 catalogue availability recovery](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/70) |
| Application candidate | `aeb48a8049138c5dd0786f2b93fd3b696c9f37bd`; committed and not deployed |
| Active staging application | `980fdc117d27a7bcf5d0d0cd950e31e74cee1497` |
| Immediate staging rollback | `33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b` |
| Deployment state | Candidate not deployed; active staging remains unchanged |
| Production | No merge, deployment, configuration or integration action authorised or performed |

No runtime setting, supplier file, database row, queue item, payment, order or notification was changed while producing this evidence.

## Redacted diagnosis

| Observation | Read-back |
|---|---:|
| Published active real NDI catalogue rows | 1,574 |
| Rows with positive recorded stock | 918 |
| Rows with fresh stock | 0 |
| Latest live real-stock `observed_at` | `2026-08-12 00:47:54` |
| Historical files in the existing restricted inbox | 3, all from 12 August |
| Active `NDI_INBOX_PATH` | Empty / not configured |
| Supplier files observed after 12 August | 0 |

The 918 positive quantities are historical, not current availability, and cannot be used for purchase. Test-fixture or unrelated import activity must not advance the live-stock freshness clock.

Two independent conditions explain the user-visible failure:

1. The active application couples public catalogue visibility to fresh positive stock, so the normalized real catalogue disappears when the supplier feed ages.
2. The application runtime is not pointed at the already provisioned restricted inbox, and the supplier has not delivered a newer authoritative file. Configuration repair alone cannot manufacture a fresh dataset from the three old files.

## Undeployed candidate contract

### Browse and discovery

- Published catalogue identities belonging to an active real supplier remain available to listing, detail, suggestions and sitemap surfaces when stock is missing, stale or zero.
- Cards/details show an explicit localized state: missing, stale, zero, local or remote.
- The explicit in-stock filter remains limited to fresh positive stock.
- When an authoritative Norway aggregate exists, its own timestamp governs the sellable quantity; a newer secondary warehouse row cannot mask a stale aggregate. Without that aggregate, every physical warehouse row contributing to quantity must be current, so one fresh row cannot mask another stale row.
- Catalogue identity visibility does not assert that an item can be bought.

### Cart, checkout and payment

- Freshness, positive quantity and sufficient quantity are checked independently of browse visibility.
- Missing, stale, zero and insufficient stock cannot be added as a valid purchasable line.
- A previously stored unavailable line resolves to an explicit cart issue and does not become checkout-ready.
- Checkout and payment readiness remain fail-closed; the recovery does not permit stale or unknown inventory to proceed.

### Environment and fixture isolation

- Production catalogue queries exclude test fixtures even if a fixture row is present or active in storage.
- The only approved sandbox fixture is SKU `NDI-TEST-4765205551649102`, quantity `2`, unit price `10 NOK`, total `20 NOK`.
- Sandbox readiness requires the isolated profile, exact product/amount contract, fresh sufficient fixture stock and a valid price. The payment contract is immutable in code, is rejected outside Dintero test, and cannot be relaxed by a mutable price setting. Ordinary or mixed orders cannot cross the test payment boundary.

### Import and health

- `ndi:inbox:sync` returns an explicit failure when its inbox path is not configured; the scheduler can no longer report a false successful no-op for this condition.
- The stored-artifact observation timestamp is initially derived from source-file filesystem modification time (mtime) and retained; import execution time is never used as a substitute for feed freshness.
- `ndi:inbox:sync` treats a hash already attached to a successful live NDI run as a duplicate across parser versions. A same-content manual parser replay may create a succeeded audit run, but retained artifact time and the older-observation guard prevent it from renewing stock freshness. Touching already-stored duplicate content cannot renew it either.
- A future observation is rejected before domain state is changed, and an older valid observation cannot overwrite newer current stock.
- Supplier freshness is derived only from a successful live, active, non-test NDI import that includes a stock dataset. Sandbox, non-stock and unrelated imports cannot mask missing/stale live stock.
- The health freshness window is additionally capped by the storefront stock-purchase window. Health cannot remain fresh after the same stock age has already made storefront purchasing fail closed.
- When a published active live NDI catalogue exists, health fails if its live stock import is missing or stale. Health also fails if the configured inbox is not a readable directory; an unconfigured inbox is never treated as operationally ready.
- Health exposes only allowlisted inbox facts: configured/directory/readable booleans, failed/retryable counts, normalized latest status and age. It does not expose a path or filename.

## Verification completed

| Check | Result |
|---|---:|
| Full Laravel regression | 589 passed, 5,550 assertions, 1 expected Windows symlink skip |
| Standard Playwright matrix, sequential | 251 passed, 132 expected conditional skips, 0 failures |
| Dedicated Issue #19 Playwright | 11 passed, 4 expected project-specific skips, 0 failures |
| Supplemental map/content matrix | 56 passed, 0 failures |
| Sequential admin dependency chain | 16 passed, 0 failures |
| Pint | Clean |
| Production asset build | Clean |
| Independent code audit | No blocking or material defect found |

The coverage includes browseable stale/missing/zero states, fresh-only in-stock filtering, mixed-warehouse timestamp masking, replay/touch/parser-version timestamp safety, future/older observation rejection, server-side cart/checkout rejection, immutable Dintero-test-only sandbox payment isolation, production fixture exclusion, explicit missing-inbox failure, live-non-test health selection and the storefront-bounded health window. The browser matrix covers buyer and admin paths, Bokmål and the hidden Russian QA route, desktop, 390 px and 320 px layouts.

CI on the final PR head, exact-head review and staging deployment are still pending. This file does not claim deployed runtime acceptance.

Two deliberate fail-closed limitations remain. A byte-identical file cannot prove that the supplier made a new delivery until a trusted delivery identifier or sequence is available and implemented, so it stays a duplicate. A complete bundle is dated by its oldest constituent mtime, which can conservatively keep stock unavailable when one dataset is older than the others. Neither limitation can make stale inventory purchasable.

## Remaining acceptance and operations work

1. Commit and push the verified candidate, pass CI, then obtain review/deployment decisions for that exact SHA.
2. After deployment, verify that representative real catalogue rows remain browseable with honest unavailable states and that cart/checkout remain blocked for the current stale inventory.
3. In a separate controlled operations step, set active `NDI_INBOX_PATH` to the existing restricted inbox and verify only redacted configured/directory/readable and receipt-state fields. This configuration repair is planned, not performed by the candidate.
4. Receive a new accepted, non-duplicate authoritative NDI stock delivery and complete a successful validated import whose oldest required bundle mtime is fresh. Byte-identical content additionally needs a trusted delivery identifier or sequence to be available and implemented. Only that external delivery can restore fresh real inventory.
5. Re-read live health and confirm that fixture/unrelated imports cannot affect live freshness before enabling any real purchase path.

The external supplier delivery remains a blocker to fresh purchasable inventory and a representative fresh-stock acceptance. It is not a blocker to catalogue browsing or demonstration once the browse-safe candidate has passed review and is deployed.
