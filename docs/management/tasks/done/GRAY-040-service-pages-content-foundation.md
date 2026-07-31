# GRAY-040 — Service pages content foundation

Owner: Mister Gray
Status: done
Model: Codex Terra

## Result

- Added the `Content` module's localized `service_pages` schema, model and publication/locale enums.
- Added a published-only read boundary for ordered service lists and slug lookup.
- Added an idempotent editorial seeder for six service-page foundations: mobile tire change, workshop, Tesla/EV, motorhome/van, tire hotel, and tires/wheels/packages.
- Added Pest coverage for persistence, unique slugs, publication filtering, localized reads, ordering and idempotent seeding.
- Documented the content model and read contract in `docs/architecture/SERVICE_PAGES_CONTENT_MODEL.md`.

## Boundaries observed

No public/admin Blade, routes, NDI/API code, price, delivery/SLA, certification or testimonial claims were changed or introduced.
