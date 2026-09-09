# Issue #45 evidence

This directory contains only generated application evidence and concise verification records for Issue #45. Customer-source photographs are not republished here.

## Source map

| Customer source | Required outcome | Implementation / test / evidence |
|---|---|---|
| `message99135–99143`, `photo_1` | Header labels, phone CTA and accessible service navigation | `components/layouts/storefront.blade.php`, `ui.js`, `storefront-v3.css`; exact NB/RU and interaction coverage in `CustomerNavigationRevisionTest.php` and `customer-navigation.spec.ts`; `header-menu-*.png` |
| `message99136` | Optional low-cost catalogue search decision | Implemented by reusing only the existing tyre `q` contract and `/dekk/suggestions` endpoint. No new index, dependency, supplier call or whole-site-search claim. Verified in NB/RU/mobile by `customer-navigation.spec.ts` |
| `message99138` | Hero lead adds `dekkskift` without replacing later operator edits | Translation fallback plus guarded `CustomerNavigationRevisionSeeder`; guarded inverse `RollbackCustomerNavigationRevisionSeeder`; exact-old, idempotence and later-edit tests in `CustomerNavigationRevisionSeederTest.php` |
| `photo_3–photo_6` | Width, profile and rim selections in shared quick finder | `_quick-finder.blade.php` and declared supplier-independent vocabulary in `config/storefront.php`; default/invalid/selected/query/NB/RU/shared-consumer tests; `finder-*.png` |
| `message99167–99170`, `photo_18` | Original logo treatment and exact footer content/order | Original `/brand/easytire-logo.png` with no filter/recolour, new backing plate and exact NB/RU footer contracts; PDF HTTP and no-JS footer checks; `footer-*.png` |
| `photo_19` | Wide two-column desktop service navigation | Accessible grouped mega menu and same mobile order; pointer, touch, Tab/Shift+Tab, Escape/focus-return and stable-geometry tests; `header-menu-*.png` |

## Service destination inventory

| Customer label | Existing destination / bounded mapping |
|---|---|
| Dekkskift hjemme hos deg | Existing seasonal request route with `fulfilment=mobile` |
| Dekkskift på verksted | Existing seasonal request route with `fulfilment=workshop` |
| Dekkhotell | Existing canonical `/dekkhotell-tromso` |
| Montering og balansering | Existing canonical `/omlegging-balansering-dekk-tromso` |
| Punkteringsreparasjon | Existing canonical `/dekkreparasjon-tromso` |
| TPMS | Existing canonical `/tpms-sensor-tromso` |
| Dekkskift for Tesla og elbil | Existing canonical `/dekkskift-tesla-elbil-tromso` |
| Bobil og varebil | Existing published service page `/tjenester/bobil-og-varebil` |
| Oljeskift | Existing canonical `/oljeskift-tromso` |
| Batteritest og batteribytte | Existing canonical `/bilbatteri-tromso` |
| Bytte av lyspærer og vindusviskere | Existing canonical `/bytte-lyspaerer-viskerblad-tromso` |
| Interiørrens | Distinct real `/tjenester#service-interior-cleaning` destination on the services directory until a standalone managed page is published |
| Polering og lakkorrigering | Distinct real `/tjenester#service-polishing-paint` destination on the services directory until a standalone managed page is published |
| Utelåsing – åpning av bil | Distinct real `/tjenester#service-lockout` destination with the existing effective phone action until a standalone managed page is published |

The same three anchors are rendered at the locale-correct `/ru/tjenester` projection. No placeholder `href="#"` or fabricated SEO article is introduced.

## Content manifest and rollback

Do not run either command without the corresponding runtime gate.

```text
php artisan db:seed --class=Database\\Seeders\\CustomerNavigationRevisionSeeder
php artisan db:seed --class=Database\\Seeders\\RollbackCustomerNavigationRevisionSeeder
```

Both directions use exact expected-old-value checks, are idempotent and preserve later operator edits. The manifest changes only `hero_text_nb` and its hidden RU QA projection. No schema migration is introduced.

## Visual evidence

- `header-menu-{1920x1080,1440x900,1366x768,1280x800,390x844,320x800}.png`
- `finder-{1920x1080,1440x900,1366x768,1280x800,390x844,320x800}.png`
- `footer-{1920x1080,1440x900,1366x768,1280x800,390x844,320x800}.png`
- `viewport-measurements.json` records menu, finder, hero and original-logo computed geometry.

Representative images were manually inspected after the final build. Desktop navigation does not wrap or resize the header at 1280/1366; the mobile menu is a bounded internal scroll region, and the last service is reachable by touch/keyboard scrolling.

## Verification

Final working-tree verification before the review commit:

- project-control validation: passed;
- `git diff --check`: passed;
- sensitive tracked-file guard (`.env`, `application/.env`, `docs/access.md`): none tracked;
- Pint: passed;
- Laravel: 431 passed, 1 skipped, 4,132 assertions;
- production Vite build: passed;
- full buyer/admin Playwright matrix: 172 passed, 53 intentionally project-scoped skips, 0 failures;
- Issue #45 viewport capture: passed at 1920, 1440, 1366, 1280, 390 and 320 pixels.

The GitHub exact-head checks remain authoritative for the reachable-history Gitleaks scan and repeat the repository gates after push. The exact review SHA and check run are recorded in the PR #48 ENGINEER UPDATE.

## Runtime boundary

Local/CI synthetic verification only. No staging write, deployment, external API call, payment, notification or real customer submission is part of this evidence package.
