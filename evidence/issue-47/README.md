# Issue #47 verification index — PR #87 refresh

This directory contains the human-reviewable evidence introduced for PR #50
and refreshed from the final PR #87 candidate. The
Playwright geometry, interaction, locale, media-failure and overflow checks
remain the source of truth; the captures below are review checkpoints.

## Customer homepage

| Viewport | Evidence |
|---|---|
| 1920x1080 | `homepage-1920x1080.jpg` |
| 1440x900 | `homepage-1440x900.jpg` |
| 1366x768 | `homepage-1366x768.jpg` |
| 1280x800 | `homepage-1280x800.jpg` |
| 800x900 | `homepage-800x900.jpg` |
| 390x844 | `homepage-390x844.jpg` |
| 320x800 | `homepage-320x800.jpg` |

Focused contact/B2B evidence is available as `business-partners-1440.png`,
`business-partners-390.png` and `business-partners-320.png`.

`homepage-layout.json` records the measured five-column desktop / one-column
narrow process timeline, equal step heights and the two-column partner-logo
grid at the same widths. The browser suite additionally proves:

- final section order and absence of the former duplicate catalogue block;
- exact customer copy in Norwegian and the hidden Russian QA route;
- the 700 kr Dekkhotell base tier;
- a real Tab journey into the details dialog in desktop, 390 px, 320 px and
  Russian QA projects, plus focus containment, Escape and focus return;
- muted autoplay, explicit pause/play, reduced-motion pause and media-failure
  fallback to the poster, including a media error raised before JavaScript
  finishes initializing;
- four local partner logos and their approved external destinations;
- no global horizontal overflow.

Stored database edge cases are captured as
`promotion-long-short-{1440,390,320}-{nb,ru}.png` and
`promotion-legacy-body-{1440,390,320}-{nb,ru}.png`. They prove that both a
long new short description and a legacy offer without short copy keep their
last sentence and CTA reachable without replacing the DOM text in the test.

## Administration

| State | Viewport | Evidence |
|---|---|---|
| Homepage copy and video assignment | 1024x768 | `admin-homepage-settings-1024x768.png` |
| Media library video preview and upload control | 1024x768 | `admin-media-video-1024x768.png` |

The E2E seed assigns `tests/Fixtures/homepage-loop.mp4` only in local/testing
environments. It is a deterministic browser fixture, not customer content and
is never installed by the production content manifest.

The settings test saves and reloads a new bilingual homepage field, rejects an
image ID injected into the video selector at the server boundary and reaches
the new controls through real keyboard traversal.

## Source and rollout notes

See `source-matrix.md`. The exact customer video `003 muzik.mp4` was not
present in the supplied Telegram export (the export recorded it as exceeding
the download limit). Production therefore keeps the approved poster fallback
until that exact source file is supplied, optimized and selected in the admin.
The rollout manifest keeps a separate first-application journal for settings
and the 700 kr tier. A later administrator edit therefore cannot be mistaken
for an untouched legacy value, and the rollback restores only values still
equal to what this release applied.

## Original PR #50 verification

- Laravel/Pest: `463 passed`, `1 skipped`, `4388 assertions`.
- Playwright: `197 passed`, `76 skipped` across the buyer, hidden Russian QA,
  admin desktop, admin 390 px, admin 320 px and stored-content edge projects.
- Vite production build: passed (`62 modules transformed`).
- Pint: passed.
- Project-control validation: passed.
- `git diff --check`: passed.

## PR #87 exact-candidate refresh

- The seven full-page homepage captures, three focused business/partner
  captures and `homepage-layout.json` were regenerated from the current
  Modern storefront candidate on 2026-10-04.
- Modern and Classic Playwright regression: `112 passed`, `32 expected skips`,
  `0 failed`.
- PR #87 buyer-focused Playwright: `17 passed`, `11 expected skips`.
- Modern/Classic responsive balance matrix: all `20` locale/profile/viewport
  combinations passed.
- Issue #26 focused audit: `1 passed`.
- Targeted Laravel/Pest: `28 passed`, `214 assertions`.
- Vite production build, Blade view compilation, deployment safety suite and
  `git diff --check`: passed.
- No migration, seed, import, product data, integration configuration or
  server-service change is part of this refresh.
