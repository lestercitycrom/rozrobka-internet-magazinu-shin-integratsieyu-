# Issue #44 cumulative release evidence

This directory is the final focused evidence supplement for the customer homepage/navigation package. It does not duplicate the accepted implementation evidence in `evidence/issue-45`, `evidence/issue-46`, or `evidence/issue-47`.

## Evidence map

| Requirement left open by the PM | Verification | Evidence |
|---|---|---|
| Natural full storefront-settings save/reload without disabling unrelated controls | The isolated browser submits more than 90 unique enabled controls, changes one new field, clears the optional video assignment, saves, reloads, and proves unrelated NB/RU values are retained. | `admin-storefront-settings-{nb,ru}-{1024x768,390x844,320x800}.png` |
| New settings controls at 1024, 390 and 320 in NB/RU | Real Tab navigation reaches locale details and the video selector; every required control is visible and the page has no global overflow. | Same admin images above; `tests/E2E/issue-44-release-qa.spec.ts` |
| Reverse Shift+Tab and focus containment in Dekkhotell details | Real keyboard traversal wraps close -> last CTA -> close; Escape closes and returns focus to the opener in NB/RU. | `tests/E2E/issue-44-release-qa.spec.ts` |
| Real touch open/close and mobile dialog readability | Touch-enabled 390 and 320 projects use `tap()`, prove `pointerType=touch`, make the final CTA reachable, close by touch, and return focus. | Full Playwright result; `tests/E2E/issue-44-release-qa.spec.ts` |
| No-JavaScript details fallback | A JavaScript-disabled context opens the native `details` disclosure and reads both paragraphs at 390/320 in NB/RU without overflow. | `tests/E2E/issue-44-release-qa.spec.ts` |
| Approved poster-only fallback while the exact video is absent | No video element/control is rendered; the approved poster, copy and CTA remain readable at 1440/390/320 in NB/RU. | `homepage-poster-only-*.jpg`, `poster-only-dekkhotell-*.png`, `poster-only-measurements.json` |

The focused Dekkhotell element images are intentionally captured after scrolling the section into view, so the fixed header can appear above the element boundary on narrow captures. The full-page `homepage-poster-only-*.jpg` files record the natural document composition and are the authoritative visual context.

## Accepted evidence reused

- `evidence/issue-45`: native 1920/1440/1366/1280/390/320 header, finder, footer, service navigation, search focus and corrected mobile directory.
- `evidence/issue-46`: offers, independently managed service collections and their admin surfaces.
- `evidence/issue-47`: final homepage composition, 4/2/1 partner grid, real long-copy and legacy body-only records at 1440/390/320 in NB/RU, and the 1024 admin composition controls.

## Verification result

- Final Laravel/Pest matrix: 466 passed, 1 skipped, 4,405 assertions.
- Full Playwright matrix: 202 passed, 83 intentional project-scoped skips, 0 failed in 6.1 minutes.
- A repeated full Playwright run after the first bounded corrections produced the same 202/83/0 result in 4.7 minutes. The final journal-backed hero correction changes only production seeding and is covered by the final PHP feature matrix below.
- Focused content apply/repeat/operator-edit/rollback matrix after the final seed-wiring correction: 25 passed, 158 assertions.
- Production Vite build: passed.
- Pint: passed with an explicit writable PHP temporary directory required by this Windows host.
- One pre-existing catalogue assertion was made order-independent: it now verifies gross/net prices by tax-mode key instead of relying on an unspecified SQL row order. Production pricing behaviour was not changed.
- Visual inspection: representative full-page and focused NB/RU desktop/mobile images inspected; no new P1/P2 visual defect found.

The final exact-head CI run remains authoritative after this evidence and the bounded corrections are committed and pushed.

## Runtime boundary

All browser and database effects above use isolated local test state. The staging inspection was read-only. No deployment, server migration/seed, queue mutation, external API call, payment, notification, or real customer submission was performed.
