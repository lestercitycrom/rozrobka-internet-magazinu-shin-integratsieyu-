# EasyTire status

Updated: 2026-09-15 — Issue #19 candidate, no deployment
GitHub baseline: `origin/main` at `47dfdee2bb9538ba70742b0c88cf1edd4a85707c`
Issue #19 corrected application candidate: `2aaec105f67455cfdcfbebe0eb7991de3322a051`; supersedes `aeb48a8049138c5dd0786f2b93fd3b696c9f37bd`, committed and not deployed
Issue #19 review: PR #70; corrected final head publication is pending
Active staging application: `980fdc117d27a7bcf5d0d0cd950e31e74cee1497`
Immediate staging rollback: `33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b`
Production: not authorised

## Environments

| Environment | State |
|---|---|
| GitHub `main` | Accepted baseline `47dfdee2bb9538ba70742b0c88cf1edd4a85707c`. |
| Issue branch | `codex/issue-19-catalog-availability` contains application candidate `2aaec105f67455cfdcfbebe0eb7991de3322a051` plus its project-control identity update. It has not been deployed. |
| Staging | Application `980fdc117d27a7bcf5d0d0cd950e31e74cee1497` remains active; rollback `33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b` remains retained. |
| Production | No deployment or merge authorisation. |

## Runtime gates

| Gate | State |
|---|---|
| Dintero | Disabled on staging. No payment was initiated by Issue #19 work. |
| NDI catalogue | 1,574 published active real rows exist; 918 have a positive recorded quantity, but 0 have fresh stock. Active staging still runs the previous visibility behaviour. The undeployed candidate restores browsing while preserving the purchase gate. |
| NDI ingestion | A restricted SFTP inbox exists with three historical files from 12 August, but active `NDI_INBOX_PATH` is empty and no newer file has arrived. No path or filename is recorded here. |
| NDI sandbox/write | Disabled on staging. The candidate constrains payment to Dintero test and the immutable fixture SKU `NDI-TEST-4765205551649102`, quantity `2`, unit `10 NOK`, total `20 NOK`, and excludes it from production. No inquiry or order was sent. |
| Notion / Telegram | Disabled. Two pending outbox records are retained and were not sent. |
| Customer email | Not enabled by this work. |
| RU storefront | Hidden QA route with `noindex`; no public language selector. |

## Issue #19 runtime snapshot

- The latest live real-stock `observed_at` is `2026-08-12 00:47:54`. A later test/fixture activity must not be treated as a fresh real NDI stock import.
- Of 1,574 published active real catalogue rows, 918 have positive historical stock and none currently passes freshness. They are not safe to purchase.
- The existing restricted inbox contains three old files dated 12 August. Active runtime configuration does not point the importer at that inbox, and no new supplier delivery after 12 August was observed.
- No runtime setting, supplier file, database row, queue item, payment, order, notification or release was changed while diagnosing or documenting this state.
- The branch candidate makes missing/stale/zero rows honestly browseable, keeps the in-stock filter fresh-positive only, rejects unavailable cart/checkout/payment paths server-side and prevents a fresh secondary warehouse row from masking stale authoritative Norway or contributing physical stock.
- The branch candidate makes an empty inbox path an explicit command failure. Its health logic uses only successful live, non-test NDI stock imports, cannot stay fresh longer than storefront stock, and exposes redacted inbox configuration/directory/readability/receipt state.
- The active staging application does not contain these Issue #19 changes. Full runtime acceptance must wait for an immutable reviewed candidate and deployment.

## Current work

- Current backend verification is green: full Laravel `590 passed / 5,574 assertions / 1 expected Windows symlink skip` and targeted Pint. Retained frontend evidence is unchanged because the correction touches no frontend files: standard Playwright `251 passed / 132 expected skips / 0 failures`, dedicated Issue #19 Playwright `11 passed / 4 expected skips`, supplemental map/content `56 passed`, sequential admin dependency chain `16 passed`, and a clean production asset build. The PM-discovered price rollback failed under a deliberate guard-removal check and passed after restoration; an independent exact-candidate audit found no blocking or material defect.
- Import freshness is anchored to the retained stored-artifact observation timestamp, initially derived from source-file filesystem modification time (mtime). Inbox duplicates remain duplicates across parser versions; retained artifact time and older-observation guards stop same-content manual replay or touched duplicate content from making old stock fresh. Future observations are rejected and older observations cannot overwrite newer current stock. Current Gross and Net prices are separately monotonic per exact supplier-SKU and price-list identity; older distinct price observations remain in history but cannot replace newer current amount, time or artifact.
- PR #70 is open. Its prior `c44b0462836938385764a5ba7e9ea0f3b72a7004` head passed all three GitHub checks before PM found the older-price rollback. CI on the corrected final review head, exact-head re-review and deployment are still pending.
- After code acceptance and deployment, a separate controlled operations step must configure the existing restricted inbox and verify only redacted readiness/receipt state.
- A new authoritative NDI stock delivery and successful import remain necessary for fresh purchasable real inventory. That external input does not block browse/demo recovery once the candidate is deployed.
- Known conservative behaviour: byte-identical content cannot count as a new observation until a trusted delivery identifier/sequence is available and implemented, and a full bundle is dated by its oldest constituent mtime. These can hold commerce closed; they cannot open it on stale data.
- Canonical details: `evidence/issue-19/catalog-recovery.md`.

This file is the short current snapshot. Historical detail belongs to merged Issues, PRs and the linked evidence.
