# EasyTire UI Polish v1 — engineer report

Issue [#73](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/issues/73), branch `codex/ui-polish-v1`. Final independent design acceptance is not performed by the implementer.

## Baseline

- Accepted main: `3b8ae190512dd897cd0133aa3c2771d0e890196c`.
- Before runtime: `95a1b76086e2d51e1aebb01911bc83a683bf78ea`, verified through SSH REVISION, `/design-revision.txt` and PM catalogue handoff. Same tree, different squash SHA.
- Source archive SHA-256: `672ACBC01005248FCD917DF2A6A5F9DDBC4106265DCA42EC509898E556B158ED` (`EasyTire_UI_Polish_v1.zip`).
- Direction: supplied fixed-role specification; [Audi Norway](https://www.audi.no/no/) hierarchy reference, not copied code/branding/assets. EasyTire colours, Barlow, media and section order preserved.
- Workspace `D:\The works\shop-ndi-design`; old `D:\The works\shop-ndi` untouched.
- Owner authorised a combined handoff and staging previews. No merge, production, DNS or external-integration authority.

## Component / consumer map

| Owner / role | Consumers | Change and boundary |
| --- | --- | --- |
| Public tokens | Every NB route/private RU projection | Normal-width Barlow roles400/600/700. Root/admin tokens unchanged. |
| Public controls | Finder, filters, cart, checkout, requests |48px, shared button/field metrics, neutral secondary, focus. Form names/routes/JS intact. |
| InlineLink | Rich text, terms/privacy, contact links | Body weight; permanent1px word underline, offset3px. |
| ActionLink / compact | Home collections/reviews/logistics, services, contacts, product/return/edit actions | Label+SVG share focus; label-only hover/focus underline. Destructive text buttons share metrics without a misleading arrow. |
| CTA link/button | Booking, purchase, call, next-step | Primary red / secondary outline; light/ghost are inverse-surface variants. |
| Dedicated navigation/icon roles | Menu, breadcrumb, category/finder tabs, carousel | Separate from content links; selected/aria states and geometry retained. |
| Shared finder | Home/store hub | No duplicate kicker/check/boxed cells. Form inner width below400px stacks,400px+ row. Categories2×2 below720px,4×1 above; one column for very narrow/enlarged text. |
| CartAction | Public headers/mobile accessible name | Server sum-of-units.48×48 anchor,18px badge inside2px top/right,0 visible,99+ cap, actual accessible total; unavailable is not false0. |
| ProductCard | Tyres/wheels/live-filter fragments | Unobstructed media, badges in content, quiet unavailable price, shared action. Purchase guards intact. |
| ProductDetail | Real/demo/no-image/unavailable | Bounded contain-image frame; no repeated brand/kicker; calm specs/availability. |
| Informational/actionable cards | Home, contact/about, services | Shared type and quiet surfaces; feature-media/compact variants. Product/price/summary stay specialised. |
| Public sharp contracts | Named public hooks/profile | No surface shadows;2px geometry. Admin block unchanged. |

Changed shared files: `design-tokens.css`, `storefront-v3.css`, `storefront-contracts.css`, public layout/Blade consumers, two display-only translations. No backend/database/seeder/dependency/runtime-config/source-JS changes. Playwright test config registers the new regression file. JS remains `app-CKCevDiM.js`.

## Reproduced baseline / type measurements

- 1440px home finder (~400px) used3+1 categories;390px used2×2. Component-width defect.
- Badge offset parent is cart `A.et-cart` (corrected measurement), not header. Badge20×20 with negative outside offsets; fixed containment without hiding0.
- HomeDisplay before:64/69.12–700 desktop;37.05/40.014–700 at390;36/38.88–700 at320. After:56/64–700 desktop,36/44–700 mobile.
- Internal H1 before:40/48–700 desktop,32/38.4–700 mobile. After:40/48–600 and32/40–600.
- Supplementary `before-extra/report.json` records finder title24/30–700 →20/28–600; home section title40/48–700 →32/40–600; home feature title32/38.4–700 →20/28–600; hero CTA16/19.2–600 →16/24–600. Initial unidentified control/skip-link probes are not field/ActionLink evidence.
- Initial viewport crops sometimes retained smooth-scroll position. Corrected captures and `before/correction-report.json` supersede them; no false marker-absent claim retained.

## Additional improvements

1. Product badges no longer cover photos or sit inside an aria-hidden link.
2. Removed duplicate brand above product title and heavyweight unavailable-price copy.
3. Bounded media prevents unusual aspect ratios dominating the buy box.
4. Removed decorative service/filter ordinals, preserving process steps/counts.
5. Reduced double whitespace after compact functional heroes.
6. Unified cart-addon secondary action, retaining selected state and add/remove wording.
7. Enlarged-text category reflow removes the hidden icon's grid track.
8. Kept carousel icon actions separate from content-link sizing.
9. Corrected light-surface receipt download to the normal secondary button and ensured readable placeholder contrast.
10. Fixed the tablet/landscape home-hero shade: it now covers the text area instead of ending at240px and leaving white copy over bright sky. Narrow-phone media composition remains intact.

## Verification ledger

- PHP public suite:140 passed,2043 assertions; SQLite`:memory:`, integrations off. Includes8 badge fixtures0/1/9/10/99/100/999/unavailable.
- Focused Playwright:15 passed; design foundation/UI Polish/product detail/empty-cart; one Chromium worker, isolated local DB.
- Admin before/after:9 pages (dashboard/service-create/orders ×1440/390/320), allHTTP200, no overflow/JS errors, zero computed-metric differences. Test login only.
- Full PHP suite:598 passed,1 skipped,5636 assertions (hermetic SQLite in-memory).
- Broad desktop:90 passed initially,8 project skips; all4 reported failures subsequently passed targeted reruns. Two were deliberately disabled local map configuration; two were obsolete locator/focus-transition synchronisation in tests. No application behaviour changed to satisfy them.
- Mobile focused:40 passed,14 project skips; the remaining focus-geometry assertion passed on rerun after comparing document coordinates rather than native focus scrolling. Cart390/320:2 passed.
- Explicit long ActionLink fixture at320px with200% text passed: wrapped label, attached SVG, focus and no global overflow. Main/contact extended matrix covers320/390/440/768/1024/1440/1920,844×390 landscape and2560px;18 states without overflow/JS errors/heavy text/short controls. Visual inspection of this matrix identified and prompted the tablet hero-shade improvement above.
- Commerce audit1440/390/320:sum-of-units9→10,1→0,reload/Back/sticky/badge click passed; actual anchor48×48,top/right2px at every state. Existing mixed-demo checkout guard verified. Normal cart/checkout captured after removing demo; no order submitted. Checkout has no computed weights above700.
- Public visual audit:51 route/width states, expectedHTTP200/404, no global overflow/JS errors/broken visible images. Residual fallback labels/process indexes corrected; final targeted6-state type audit has no heavy/Condensed/short controls.
- Production build, PHP/Pint and fail-closed preview safety mocks passed. JS hash unchanged. No new source dependency.
- Solid-surface contrast ratios: white/primary5.54:1; meta/white5.42:1; meta/warm4.86:1; field boundary/white3.61:1; focus/white8.49:1; accent/dark6.06:1. These are measured token pairs, not a claim of exhaustive contrast over every photograph.

## Evidence / repeatability

Checked-in evidence: `before-home-1440.png` / `before-home-390.png` are corrected first screens of `/`; `before-contact-1440.png` / `before-contact-390.png` are full `/kontakt` pages on baseline runtime95a1b760. Matching `after-*` files are local candidate8045 with the same public content at1440×900 /390×844 (not a claim about supplier fixture parity). `after-home-844-landscape.png` shows the corrected tablet shade. `interaction-390.webm` records the safe local cart/checkout sequence. Chromium; fonts awaited; synthetic commerce data only. Exact candidate SHA is the containing commit, subsequently named by the preview handoff.

Ignored local artifacts under `application/storage/app/qa-ui-polish/`: `before/report.json`, `before/correction-report.json`, corrected baseline PNGs, `admin-before/report.json`, `admin-after/report.json` (metricDiffs), and intermediate iterations. `iteration-2` is not final evidence.

`tests/Audit/ui-polish-capture.mjs` generates public-after/staging-after screenshots/computed metrics with explicit QA_BASE/QA_RUN. GET/HEAD only; external providers/mutations blocked. Regressions: `tests/E2E/ui-polish.spec.ts`, updated `design-foundation.spec.ts`, `tests/Feature/Storefront/StorefrontCartBadgeTest.php`.

Use `QA_MATRIX=extended QA_NAMES=home,contact` for the additional viewport matrix. Reports include CSS viewport and Chromium version; main/contact/store/catalogue/product also have readable first-screen captures beside full-page images. No real-device result is inferred from Chromium emulation.

Commerce evidence: `commerce-after/` (cart, checkout, guarded cart at1440/390/320, `report.json`, `interaction-390.webm`), reproduced by `node tests/Audit/ui-polish-commerce.mjs`. Hard-bound to local8045; only cart mutations allowed, checkout submission blocked.

## UP/PQA disposition

UP-01–11 implemented and technically checked as above. UP-12 engineer delivery is prepared; independent design acceptance remains external. “PASS” below means engineer verification, never PM approval.

| PQA | Engineer result / evidence |
| --- | --- |
|01|PASS:accepted/base/runtime, source archive, component map, before/corrected captures.|
|02–05|PASS:shared Inline/Action/CTA, contact label-only underline and correct routes;focus/hover tests;200% reflow.|
|06–09|PASS:finder viewport320/390/440/768/1024/1440/1920 and explicit400/720 component boundaries;no3+1.|
|10–11|PASS:keyboard/tab selection retention, dimension query and four category destinations.|
|12|PASS safe-disabled lookup fallback, backend validation/error contracts;live provider outcome remains unavailable by existing integration gate, not enabled for design QA.|
|13–14|PASS:PHPserver fixtures0/1/9/10/99/100/999/error +browser presentation geometry.|
|15–18|PASS:real local cart9→10/1→0,reload/Back/sticky/click;sum of units;accessible actual total and unavailable state.|
|19–20|PASS:computed role before/after;final residual typography audit, no Condensed.|
|21|PASS for measured solid-surface contrast, long labels and200% reflow;photo overlays visually inspected, not exhaustive per-pixel certification.|
|22–24|PASS:controls, tabs, navigation/focus/disabled/active tests and breadcrumbs.|
|25–27|PASS engineer visual inspection:shared card roles, SVG/icons, preserved specialised cards;no double shadow/outline treatment.|
|28–29|PASS:focus/contrast pairs, error/form tests,320px and48px important controls.|
|30–33|PASS:public51-state coverage pluscart/checkout/error handling;home composition/media/video and form/filter regressions.|
|34|PARTIAL:Chromium touch emulation/reduced-motion/video tests pass;real iOS/Android NOT RUN.|
|35|PASS:accepted main already includes admin work;no admin files/root styles changed;9 before/after admin comparisons unchanged. No new merge performed.|
|36|PENDING independent PM/owner review on exact published preview SHA. Build/tests/evidence complete;authorised deployment is a separate preview, not final acceptance.|

Staging read-back and exact opened SHA are posted in the Issue/PR after activation. The app never deploys from a dirty checkout. No claim of production readiness bypasses existing stock, payment or operational gates.
