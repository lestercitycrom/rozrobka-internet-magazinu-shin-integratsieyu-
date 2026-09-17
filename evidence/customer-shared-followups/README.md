# Shared customer follow-ups, 17 September 2026

Source: the owner's `ChatExport_2026-09-16/messages.html` (Shop DPI, 16 September)
and its attached photos. This file records the requested scope, not a claim that
implementation or deployment has passed until the verification section is filled.

The source code is shared by Modern and Classic; each keeps its own design
profile and independent release. Branch `codex/customer-shared-followups` starts
at `f776dbe4710f001c24ba8848739f331cc15bbe96` (PR #83). Neither PR #83 nor its
predecessors are merged by this task.

## Request mapping

| Customer message | Shared action |
| --- | --- |
| 102062–102063, photos 35–36 | Open Dekkhotell / mobile-service descriptions over the respective media card. |
| 102065, photo 37 | Apply the same card-local description behavior to current offers. |
| 102070, photo 41 | Snapchat uses the existing administrator-editable social setting. No account URL was supplied; the owner is asking the customer. Per the owner's follow-up, show a visible Snapchat placeholder with “link coming soon”, automatically replaced by the saved URL. Do not fabricate a destination. |
| 102071, photo 42 | Use the explicitly accepted 2 × 2 partner-logo arrangement. Keep the supplied original logos and their links; do not recolor or distort brand artwork. |
| 102073, photo 43 | Norwegian navigation label `Kontakt Oss`; corresponding Russian label `Связаться с нами`. |
| 102075, photo 44 | Header search finds site services/content/prices rather than only tyres. Keep catalogue search available separately. |
| 102079–102080, photos 45–46 | Editable quantity on purchasable ordinary catalogue cards. |
| 102081–102086, photo 47 | Preserve supplier-approved fixed quantity 2 for the test SKU. The owner explicitly explained this behavior; it is not an ordinary-product restriction. |

Excluded by the current owner instruction: restoring the old finder/form
(102056), heavier typography (102058/102093), portrait media proportions
(102059), and old red decorations (102067/102069). These remain intentional
Classic/Modern differences, not shared changes. No new global redesign.

Voice messages in this export have no audio files; their content is not inferred.

## Data and release boundaries

- Keep the shared catalogue, prices, stock, content and integration configuration.
- No migrations, reseeding, catalogue imports, worker/scheduler duplication,
  external integration activation or live form/order submissions.
- New search is read-only and indexes only public content.
- Tests that mutate carts/content use isolated local fixtures, not staging data.
- Publish the reviewed source SHA, build/test, and deploy that same source to
  both profile-specific roots with each previous release retained for rollback.
- Preserve each host's cookie/cache/storage isolation and Classic's canonical
  host, noindex and admin redirect. Never use the legacy main-branch deploy hook.

## Verification

Local checks completed before publication:

- Full Laravel regression: 668 passed, 6,139 assertions; one existing Windows
  symlink skip. The additional search-settings case and final touched components
  were then checked together: 47 passed, 613 assertions.
- PHP formatting for application source/routes/translations/tests passed.
- Vite production build passed (66 modules); Classic's separate stylesheet is
  unchanged (`storefront-classic-jOP6mQnI.css`).
- Project-control validation and `git diff --check` passed.
- Migrated legacy browser journeys (homepage content/composition/feedback and
  customer navigation): 24 passed, 13 intentional project/viewport skips, no
  failures; local SQLite on port 8076 with outgoing integrations disabled.
- The dual-deployment offline safety suite passed, including rejected scope/env
  drift, one isolated build, Classic session continuity and rollback of both
  hosts if the second activation fails.

Browser verification covers both design profiles, NB/RU, and 1440/390/320 px.
Evidence is produced under `application/tmp/customer-shared/evidence/`; CI runs
the snapshot matrix and a separate isolated purchasable-fixture quantity matrix.
No staging orders or integration requests are submitted by these tests.

Exact published SHA, final browser/CI results and live read-back are recorded in
the pull-request conversation after publication. Snapchat itself is visible;
only its real destination remains pending the customer's account URL. The
existing administrator setting and automatic placeholder replacement are covered
by both-profile tests.
