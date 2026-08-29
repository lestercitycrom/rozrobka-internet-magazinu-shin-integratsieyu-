# EasyTire website requirements baseline

Updated: 2026-08-29

Scope: the website initiative only; Notion is postponed

Canonical atomic register: [`WEBSITE_REQUIREMENTS_TRACEABILITY.csv`](WEBSITE_REQUIREMENTS_TRACEABILITY.csv)

## Purpose

This is the current requirements baseline after reconciling the original brief, the complete Freelancehunt and Telegram correspondence, the correction PDF, the SEO workbook, customer media, provider replies, permanent project decisions and accepted implementation.

The 114 rows in `WEBSITE_REQUIREMENTS_TRACEABILITY.csv` are incorporated into this baseline by reference. Each row is one atomic, independently classifiable requirement with a source, implementation evidence, verification method and current disposition. This document defines the consolidated product contract and resolves contradictions between older documents.

## Authority and interpretation

1. Permanent accepted decisions in `project-control/DECISIONS.md`.
2. The latest explicit customer/owner decision tied to an exact message, file, page or voice identifier.
3. Active PM decisions in Issues #9 and #10.
4. Canonical customer documents and provider replies.
5. Earlier TZ text and unaccepted design/developer proposals.

Nothing is promoted from an idea to a requirement without customer/owner acceptance. Missing production credentials, supplier data, commercial values or usage rights are external prerequisites or decisions, not invented implementation defects.

## Current product contract

### Product, localization and visual system — `WEB-*`, `LOC-*`, `DES-*`, `NAV-*`

- EasyTire is one modern Norwegian site combining a local seasonal-service business with a tyre/wheel shop.
- Seasonal service is the primary conversion path; the complete shop path is visible immediately.
- Public indexable copy is Norwegian Bokmål. Russian exists only as a hidden QA route, is `noindex,nofollow` and is not exposed as a public language selector.
- Customer corrections govern wording: avoid generated `É/én`; use `dekkskift hjemme hos deg`, not `mobilt dekkskift`.
- The accepted storefront is the sharp version: strong rectangular geometry, red/black/white palette, warm local-workshop tone, readable typography, alternating dark/light sections and restrained motion. References are inspiration, never templates to clone.
- Desktop, 390px and 320px layouts must be usable without horizontal overflow, squeezed controls or inaccessible navigation.
- Header/footer/contact facts, cart state, service navigation, legal links, social links and branding must stay coherent across pages.

### Home, content and local trust — `HOME-*`, `MEDIA-*`, `SEO-*`

- The hero presents one strong seasonal CTA and a complete adjacent shop finder.
- Home includes Dekkhotell near the hero, a compact four-step process, price preview, image-led service/promotion content, reviews, contact/map/social proof and local-team/workshop imagery in the accepted order.
- The current direction alternates dark and light sections; it must not make the entire site dark.
- The map loads immediately and points to the customer-confirmed EasyTire destination; it is not hidden behind an unnecessary consent-style placeholder.
- Customer-supplied workshop/team/service media is preferred. Missing supplier product images use a neutral honest placeholder, never a fabricated product image.
- Promotions for two cars and early October remain unpublished until value, dates and terms are approved (`DEC-01`).
- B2B partner logos require customer approval and usage rights (`EXT-01`).
- The SEO workbook is readable and authoritative for its 11 routes, headings, metadata, copy and form intent (`STALE-01` corrects the older PM note).

### Shop, catalogue, fitment and pricing — `SHOP-*`, `CAT-*`, `PRICE-*`

- Users can start from registration lookup or manual tyre/wheel dimensions, browse categories and use live filters/autocomplete without a full-page submit loop.
- Catalogue cards expose season, dimensions, brand/model, availability/lead-time meaning, price, honest image state and cart action in a spacious responsive grid.
- NDI products are normalized into a local catalogue. Real products may be published only while the supplier feed is inside the configured freshness window; stale stock must fail closed.
- One isolated sandbox product may participate in the full staging purchase flow without contaminating production NDI records.
- Local vs remote supplier stock and tentative lead time must be communicated honestly; final availability is confirmed before fulfilment.
- Automatic tyre size guidance may use Statens lookup when configured. Wheels/full kits must not claim compatibility without licensed PCD/DIA/fitment data (`EXT-02`).
- TPMS is informational/opt-in until official applicability and programming data exists (`EXT-03`).
- Production image coverage depends on official NDI/customer assets (`EXT-04`).
- Old DOT/production-year handling needs an approved hide-or-label policy (`DEC-02`).
- Production environmental fee/VAT/source-price/markup/rounding formula needs explicit written approval (`EXT-05`). Markup and relevant storefront settings are administered through the custom EasyTire admin.

### Service requests, booking and operations — `FORM-*`, `BOOK-*`, `ADMIN-*`, `OPS-*`

- Seasonal requests support one or two cars, known or unknown size, Dekkhotell status/renewal and exact-price or range-price outcomes.
- Service forms retain the SEO-workbook-specific fields and validate contact details server-side. The battery form specifically requires problem, registration number, desired date, name and phone.
- Booking capacity must account for two mobile vans. The allocation algorithm remains a deliberate decision: area allocation or fill one van before the second (`DEC-03`).
- The custom admin, not Filament, manages catalogue/imports, service requests, bookings, orders/payment operations, pricing/settings, home content/promotions and activity history within role/CSRF/auth boundaries.
- The site must keep queue, scheduler, database, cache, supplier freshness and disk health observable. A stale supplier feed must not silently present stale stock.
- Notion and Telegram adapters remain disabled in this website initiative. Notion is postponed (`STALE-02`); Telegram activation requires credentials and a separate decision (`EXT-09`).

### Checkout, Dintero and NDI fulfilment — `CART-*`, `CHECKOUT-*`, `PAY-*`, `NDI-*`, `LEGAL-*`

- Cart quantity is editable within stock/business rules. Checkout validates Norwegian contact/address data, shows VAT/merchant/legal facts and records required consents.
- Dintero sandbox supports the complete staging authorization flow. Production real-money activation requires production KYC/account/client/profile/signature approval (`EXT-06`).
- Payment is authorize-first. NDI goods are delivered to EasyTire, not directly to the buyer. Capture happens only after the order is genuinely ready under the accepted fulfilment rules; void/refund/reconcile remain auditable admin operations.
- A durable production customer confirmation must be delivered after the authoritative order state transition (`EXT-07`).
- Commercial NDI C1 remains disabled unless explicitly authorized. Supplier status/DESADV reads require a returned supplier reference and never guess state (`EXT-08`).
- The site publishes merchant identity, privacy, purchase/returns/cancellation/complaints/payment/delivery terms appropriate to the accepted Dintero review package. Final legal/commercial approval belongs to the merchant.

## Current exceptions requiring action

| ID | Priority | Current exception | Bounded resolution |
|---|---:|---|---|
| `FND-01` | P1 | The staging NDI dataset is beyond the freshness threshold, so the correct fail-closed gate hides the real catalogue and leaves only the isolated test SKU public. | Restore supplier inbox/feed delivery and a successful import; verify freshness monitoring, tyres, wheels and images without weakening the gate. |
| `FND-02` | P2 | Battery service request currently allows registration number and desired date to be omitted, contrary to the SEO workbook. | Make the two fields required for this service on UI and server, add localized validation and tests. |
| `FND-03` | P2 | Staging disk is below its configured warning threshold. | Reclaim safe space, preserve releases/logs/backups according to runbook and re-run health verification. |

## Explicitly outside the current implementation pass

- Notion workspace restructuring or activation.
- Production payment, supplier or messaging credentials.
- Unapproved promotion values, DOT policy or booking allocation algorithm.
- Unlicensed wheel/full-kit or TPMS compatibility claims.
- Unauthorized partner logos or fabricated product images.

## Change control

Any future change must identify affected requirement IDs, cite new customer/owner evidence, update the traceability row and receive the protocol-required PM review on the exact commit SHA. An older summary alone cannot supersede this baseline.
