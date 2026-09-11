# Issue #47 verification index

This directory contains the human-reviewable evidence for PR #50. The
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

`homepage-layout.json` records the measured 4/2/1 process-grid structure and
equal card heights at the same widths. The browser suite additionally proves:

- final section order and absence of the former duplicate catalogue block;
- exact customer copy in Norwegian and the hidden Russian QA route;
- the 700 kr Dekkhotell base tier;
- keyboard-opened details dialog, focus containment, Escape and focus return;
- muted autoplay, explicit pause/play, reduced-motion pause and media-failure
  fallback to the poster;
- four local partner logos and their approved external destinations;
- no global horizontal overflow.

## Administration

| State | Viewport | Evidence |
|---|---|---|
| Homepage copy and video assignment | 1024x768 | `admin-homepage-settings-1024x768.png` |
| Media library video preview and upload control | 1024x768 | `admin-media-video-1024x768.png` |

The E2E seed assigns `tests/Fixtures/homepage-loop.mp4` only in local/testing
environments. It is a deterministic browser fixture, not customer content and
is never installed by the production content manifest.

## Source and rollout notes

See `source-matrix.md`. The exact customer video `003 muzik.mp4` was not
present in the supplied Telegram export (the export recorded it as exceeding
the download limit). Production therefore keeps the approved poster fallback
until that exact source file is supplied, optimized and selected in the admin.
