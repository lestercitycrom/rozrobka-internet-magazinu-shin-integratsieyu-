# WHITE-040 — Public catalog experience

Owner: Mister White
Status: done
Model: Codex Terra

## Goal

Replace the public placeholder with the first genuinely testable Norwegian storefront slice, aligned with the EasyTire brief and current brand rather than the monochrome admin direction.

## Scope

- Build a modern responsive public shell using the current EasyTire white/near-black/red brand direction and Tromsø positioning.
- Implement catalog landing, tire listing, wheel listing and product detail routes/views.
- Use the normalized catalog database through an injected/read-model boundary; do not hardcode product rows into views.
- Expose useful manual filters supported by the current schema, pagination, clear empty states and query-string persistence.
- Provide buyer CTAs for adding a purchasable product to a session cart boundary if that boundary already exists; otherwise keep the CTA integration-ready and cover the current behavior honestly.
- Preserve Norwegian public copy and accessibility basics.
- Extend buyer Playwright coverage for desktop and mobile.

## Boundaries

- Do not modify the custom admin.
- Do not invent current availability, delivery promises, discounts, reviews or legal claims.
- Do not create a permanent logo until Mister Red completes the brand-asset audit.
- Do not call external APIs from browser code.
- Coordinate contracts with the Catalog read model; do not own import logic.

## Acceptance

- A visitor can browse and filter fixture-backed tires/wheels and open a product page.
- Empty and populated states are both covered by feature tests.
- Buyer Playwright validates meaningful interactions on desktop/mobile.
- Build and relevant tests pass.

## Completion note

- The shared `app.css` tokens use the audited EasyTire palette: white/near-black with `#ff0000` accent, plus Ubuntu with Arial/system fallback.
- The public header has an accessible asset slot for `/brand/easytire-mark.svg`; the final mark is supplied separately and no current-site logo asset is reused.
- The supplied service hero is rendered with explicit intrinsic dimensions, descriptive Norwegian alt text and a contrast overlay; storefront metadata links to the supplied favicon, touch icon and web manifest.
- Visual follow-up: public controls use a scoped, high-visibility keyboard focus ring; services links work from catalog pages; the browser test verifies the brand mark and icon metadata.
- Russian preview follow-up: `/ru` routes are gated by `STOREFRONT_RU_PREVIEW_ENABLED` and non-production environments, use request-only locale middleware, are `noindex,nofollow` with NB canonical URLs. No sitemap exists in this repository, so no sitemap entry was required.
