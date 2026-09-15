# Design v2 — S1 reference preview

Date: 2026-09-15. Issue #44. Branch `codex/issue-44-design-v2`, based on accepted `bf7f03e3d3265174e4e4307064769a742dd087cc` in the separate `shop-ndi-design` clone. The previous working folder remains untouched.

Source: `EasyTire_UNIFIED_TZ_v2_2026-09-15.zip`, SHA-256 `4234EE9F20D5946A42E56E21DA0CDF4EB56B365446823ADC92596CF253923E90`. This is a reference-fragment preview, not completion of ET-01–42 or final visual acceptance.

## Included

- Public DS-1 typography roles, 1280px container, responsive gutters/spacing, principal 48px controls and sharp geometry, scoped away from the admin design tokens.
- Home first screen: full-width photograph, desktop copy with a 400px finder on the right, stacked tablet layout, 220px mobile image followed by the offer/CTA and finder.
- Catalogue reference: compact functional heading, 280px desktop filter rail, three-column bounded product grid, readable unclamped product titles and principal card controls.
- Service reference: compact functional heading and responsive text/photo composition.
- Request reference: service-specific heading, compact introduction, visible current step label, form before supporting context in both DOM and mobile layout. Existing submission fields, validation, routes and booking semantics are preserved.
- Narrow staging-preview command authorised by PM comment 5673643971. It activates the exact published SHA in a separate release without migration, seeding, pruning, integration changes or replacing shared data/media; previous and accepted releases remain available.

## Engineer-found fixes (separate from requested composition changes)

1. Removed conflicting later CSS that silently restored an oversized mobile hero/catalogue heading and forced the desktop finder below the hero despite the earlier grid rules.
2. Removed the conflicting intermediate-width two-column hero rule; tablet layout now follows the intended stack.
3. Corrected heading-role cascade so H1/H2/H3 do not all inherit the H1 size.
4. Corrected generic request DOM order as well as its visual order, keeping keyboard navigation aligned with the form-first mobile layout.
5. Added a dark-red focus outline on light hero/finder surfaces where the previous white outline lacked contrast.
6. Kept the store navigation label stable across tyre/wheel pages and corrected breadcrumb baseline alignment.

## Verification

Local QA uses a newly installed isolated application and synthetic SQLite catalogue, with external integrations disabled. It does not use the old working folder or staging data.

- Production frontend build passed.
- Three new `design-foundation.spec.ts` cases passed: responsive typography/hero geometry, fixed store navigation, and request current-step progression.
- Three focused existing cases passed: navigation/finder composition, service/request composition and validation, and large-screen catalogue geometry.
- Reference captures cover home, `/dekk`, `/tjenester/dekkverksted`, and `/tjenester/mobilt-dekkskift/foresporsel` at 1440×900 and 390×844, plus selected 320px, 767/768px and 1024px checks. Captures and measurements are generated under ignored `application/storage/app/qa-design-v2/` by `tests/E2E/design-reference.mjs`.
- Desktop/mobile references were visually inspected by the chief engineer. Automated viewport emulation is not a real-device test or independent visual acceptance.
- Preview script received a separate read-only safety review; Bash syntax, whitespace and the temporary-only safety harness passed. The harness accepts a CSS-only candidate, rejects backend changes and verifies that read-only checks invoke no runtime commands. The live published SHA and post-deployment result are reported in the design PR, not inferred from this file.

## Still in progress

Full home section ordering, paired Dekkhotell/video block, promotions, additional services, contact/footer refinements, catalogue states and remaining ET acceptance cases are not claimed complete. The received original `003 muzik.mp4` is preserved (SHA-256 `899B30B01FE0D5358EB873639E036C11201E3D8F1EE0A5F02245276D94CEA5F0`); an optimised silent clip/poster is prepared locally but is not yet connected to this preview. No Snapchat URL was found in either supplied customer export; no link or business content has been invented. Two customer audio messages remain untranscribed.

Owner follow-up on 2026-09-15 supersedes the intermediate external visual gate: complete the full design assignment, reconcile customer requirements and engineer-found improvements, then hand one combined list and staging version to the owner/ChatGPT Pro. Do not wait for an external S1 review before continuing. Source exports and large raw media remain outside Git. This fragment changes no backend, API catalogue implementation, database schema or existing CRM workflow.
