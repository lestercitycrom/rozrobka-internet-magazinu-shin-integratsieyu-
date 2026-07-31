# Media library

## Safety boundary

Media assets are application-managed uploads only. The library accepts JPEG, PNG and WebP raster files, with Laravel file/image validation, an 8 MB limit, MIME allowlist and server-side image-dimension decoding. SVG and GIF are outside the allowlist. Files are stored on the public disk under randomized `media/` paths; client filenames are retained as metadata only and are never used as the public storage path. Existing generated brand assets are not managed by this library.

`media_assets` uses ULID identities and records disk, randomized path, original name, MIME, byte size, dimensions, SHA-256, localized alternative text and uploader. Media administration remains behind the existing active-admin middleware.

## Assignment contract

`media_assignments` is polymorphic. Its unique `(assignable_type, assignable_id, role)` key permits one role per entity. Reserved intended roles are `hero` for service/content pages and `primary` for catalog products. Assignment UI and storefront rendering are intentionally not yet delivered; no caller should assume that an assignment is currently selectable or rendered.

## Localized alternative text

Assets carry `alt_nb` and `alt_ru`. When storefront rendering is introduced, it must select the active locale and fall back to Norwegian Bokmål, then an empty alt attribute. It must not invent descriptive text. Images should retain fixed aspect-ratio layout, use `loading="lazy"` where non-critical, and leave existing text-only fallbacks intact.

## Deletion policy

An assigned asset cannot be deleted: the admin endpoint returns HTTP 409 and leaves both database row and storage object untouched. An unassigned asset is removed from its configured storage disk before the database record is deleted. The policy prevents accidental deletion of in-use media; future assignment management must preserve it.

## Acceptance matrix

| Requirement | Current state |
|---|---|
| ULID metadata and polymorphic unique role assignment | Implemented |
| JPEG/PNG/WebP, 8 MB, SVG rejection | Implemented and feature-tested |
| Randomized storage / no public original filename | Implemented and feature-tested |
| In-use deletion protection / unassigned cleanup | Implemented and feature-tested |
| NB/RU alt editing UI | Not yet implemented |
| Service/content hero and product primary assignment UI | Not yet implemented |
| Storefront locale-alt rendering and fallback | Not yet implemented |
| Full admin NB/RU translation coverage | Not yet implemented |
