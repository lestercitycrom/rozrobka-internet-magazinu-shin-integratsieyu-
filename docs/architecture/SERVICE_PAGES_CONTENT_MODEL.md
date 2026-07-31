# Service pages content model

The `Content` module owns editable service-page content. `service_pages` has a stable, unique `slug`, editorial `sort_order`, publication status, timestamps, and independent `nb`/`ru` fields for title, excerpt, body, SEO title, and SEO description.

`PublishedServicePageReadService` is the public read boundary:

- `list(ServicePageLocale $locale = NorwegianBokmal)` returns only published pages in `sort_order`, then ID order;
- `findBySlug(string $slug, ServicePageLocale $locale = NorwegianBokmal)` returns only a published page or `null`.

The read item contains only the selected locale's content. Draft and hidden records are never returned. This boundary makes templates independent from the content table and leaves all editing/authorization work for a later admin action.

`ServicePageSeeder` supplies six original, conservative editorial foundations. It uses `firstOrCreate`, so a later editorial change is not overwritten by a re-run. The seed content makes no prices, delivery/SLA, certification, review, or performance claims.
