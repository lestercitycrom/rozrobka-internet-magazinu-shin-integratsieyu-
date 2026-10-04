# EasyTire engineering workflow

Updated: 2026-10-04. Authority: [owner/PM decision in #44, comment 5670860452](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5670860452), amended by the [standing staging decision in PR #87, comment 5979629377](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/87#issuecomment-5979629377).

Version: 1.1 (retained governance contract; execution sequence amended by the linked owner decisions, including the standing staging rule).

## Three truth domains

Requirements and acceptance are recorded in the authorised Issue; implementation is proven by the exact Git SHA and its tests; runtime is proven by a fresh server read-back. Neither a document nor a merge substitutes for runtime evidence.

## Merge and runtime gates

1. Implement the assigned user-visible result in one bounded branch/PR from accepted `origin/main`.
2. Run focused local checks for changed scenarios. Run final CI on the final candidate; do not repeat passed unrelated matrices or unchanged screenshots.
3. Send one concise result with the PR/version, checks and real remaining work. Routine branches, commits, PRs, CI and relevant documentation need no separate permission or prescribed report title.
4. PM reviews the exact code and user scenario for acceptance. This review is independent from the standing permission to publish safe candidates to the existing staging site.
5. After the exact pushed SHA has proportionate green checks and a bounded safe deployment path, publish it to staging, verify the runtime revision and primary routes, and hand it over without requesting another staging permission. An already proven successful step is not repeated merely for paperwork.

The owner supplies business choices and source materials. The chief engineer owns implementation and testing; PM independently accepts it. Owner follow-up on 2026-09-15 removes the intermediate external S1 visual gate: finish the full design scope and requirement reconciliation, then hand one combined list and staging version to the owner/ChatGPT Pro for review.

Design-preview exception: [PM #44, comment 5673643971](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/44#issuecomment-5673643971) authorises intermediate published-SHA design deployments to the existing staging without separate approval or merge. Commit/push → build and focused checks → no-migration preview → announce SHA/pages in the design PR. Coordinate appearance directly with the designated visual reviewer; preserve accepted and previous releases, data, catalogue and runtime settings. This is not final acceptance.

Standing staging rule: the owner's [PR #87 comment 5979629377](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/pull/87#issuecomment-5979629377) extends the no-repeat-permission rule to ordinary authorised EasyTire staging work. Staging publication is the default review handoff, not a claim of merge, customer acceptance or production readiness. Production and real external side effects remain separately gated.

Block on data loss/mix-ups, access-control violations, wrong storage/price/booking operations or a broken primary path. Cosmetic and optional improvements do not hold a release hostage. Continue independent authorised work while a PR is reviewed.

Safety boundaries remain: preserve user files and existing releases/backups; no blind reset/restore. Stop only the unsafe action on a demonstrated partial migration or data risk and report facts to PM. This workflow does not authorise production/DNS, payments, NDI, Notion, Telegram, mailings, new access recipients or deployments outside an explicit release/preview authorisation.

The existing EasyTire admin remains the single CRM. No parallel CRM, reconnecting the old site or duplicate implementation of accepted #65/#66.
