# Media library

## Safety boundary

Media assets come from two controlled sources: administrator uploads and the official NDI product-image endpoint. Administrator uploads accept JPEG, PNG and WebP raster files, with Laravel file/image validation, an 8 MB limit, MIME allowlist and server-side image-dimension decoding. NDI downloads use an exact host/path/UUID allowlist, HTTPS, disabled redirects, transfer-time byte limits, MIME and decoded-image validation, pixel limits and SHA-256 deduplication. SVG and GIF are outside both allowlists. Admin files use randomized `media/` paths; validated NDI files use content-addressed `media/ndi/` paths and are never hotlinked from the storefront.

`media_assets` uses ULID identities and records disk, path, original name, MIME, byte size, dimensions, SHA-256, localized alternative text and uploader. `uploader_id` is null only for supplier-managed media. `product_media_sources` records NDI provenance, download status, bounded retry state and the linked media asset. Media administration remains behind the existing active-admin middleware.

## Assignment contract

`media_assignments` is polymorphic. Its unique `(assignable_type, assignable_id, role)` key permits one role per entity. Reserved roles are `hero` for service/content pages and `primary` for catalog products. Product primary media is rendered in catalog cards and product details. Supplier synchronization may fill an empty primary assignment, but never replaces a different existing primary chosen by an administrator.

## Localized alternative text

Assets carry `alt_nb` and `alt_ru`. When storefront rendering is introduced, it must select the active locale and fall back to Norwegian Bokmål, then an empty alt attribute. It must not invent descriptive text. Images should retain fixed aspect-ratio layout, use `loading="lazy"` where non-critical, and leave existing text-only fallbacks intact.

## Deletion policy

An assigned asset cannot be deleted: the admin endpoint returns HTTP 409 and leaves both database row and storage object untouched. Supplier-managed assets referenced by `product_media_sources` are protected in the same way even when temporarily unassigned. An ordinary unassigned admin asset is removed from its configured storage disk before the database row is deleted.

## Acceptance matrix

| Requirement | Current state |
|---|---|
| ULID metadata and polymorphic unique role assignment | Implemented |
| JPEG/PNG/WebP, 8 MB, SVG rejection | Implemented and feature-tested |
| Randomized storage / no public original filename | Implemented and feature-tested |
| In-use deletion protection / unassigned cleanup | Implemented and feature-tested |
| NB/RU alt editing UI | Implemented |
| Product primary assignment and supplier fallback | Implemented |
| Storefront locale-alt rendering and fallback | Implemented |
| Full admin NB/RU translation coverage | Not yet implemented |
