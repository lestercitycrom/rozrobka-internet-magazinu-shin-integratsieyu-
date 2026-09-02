# Issue #30 evidence

This directory contains the review evidence for the bounded home hero/finder and four-step process correction.

## Baseline

- Exact accepted base: `0678fe39964292648c52ab97119b9977acbeaba2` (Issue #29 / PR #36 merge).
- `before-hero-*.jpg` was captured from that exact SHA in a detached local worktree at the six required native viewport sizes.
- The accepted audit reference for the former process rail is `evidence/issue-26/34-home-process-strip-1440.jpg`.
- The accepted audit overlays and broader matrices remain in `evidence/issue-26/01-home-desktop-matrix.jpg`, `evidence/issue-26/02-home-mobile-matrix.jpg` and `evidence/issue-26/33-hero-finder-brand-overlap-1440x900.jpg`.

## After evidence

- `after-hero-1920x1080.jpg`
- `after-hero-1440x900.jpg`
- `after-hero-1366x768.jpg`
- `after-hero-1280x800.jpg`
- `after-hero-390x844.jpg`
- `after-hero-320x800.jpg`
- `after-process-1440x900.jpg`
- `after-process-1280x800.jpg`
- `after-process-390x844.jpg`
- `after-process-320x800.jpg`

`hero-geometry.json` records the rendered media, finder, content, primary-action and both fail-closed focal boxes for every viewport. Every recorded finder/brand, finder/vehicle and finder/content intersection area is zero. `process-geometry.json` records the four required process-strip sizes and step counts.

The screenshots use only repository-controlled public EasyTire media and seeded synthetic storefront data. No customer, payment, NDI, Dintero or other private/runtime data is present.

## Regeneration

From `application/`, with the repository's PHP and Node dependencies available:

```powershell
$env:CAPTURE_ISSUE_30='1'
$env:PLAYWRIGHT_PHP_BINARY='<php-8.3-path>'
node node_modules/@playwright/test/cli.js test home-hero-process.spec.ts --project=buyer-desktop --workers=1
```

The focused test fails closed if an approved hero asset changes, either focal contract is absent, the finder intersects the branded van or vehicle/work focal region, a primary action leaves the first viewport, or a required layout overflows.
