# GRAY-090 — Secure media library and assignments

Status: done

## Delivered

- `media_assets` and polymorphic `media_assignments` schema/models;
- explicit active/archive state for media assets;
- admin upload/list/delete routes, randomized public paths, raster allowlist and 8 MB validation;
- assigned-asset delete protection and unassigned storage cleanup;
- localized alt editing and NB/RU admin copy;
- safe hero/primary assignment and detach controls for service, content and product records;
- storefront card/detail/hero rendering with locale alt fallback and stable aspect ratios;
- focused tests for access, upload safety, assignment lifecycle and public rendering;
- architecture safety contract in `docs/architecture/MEDIA_LIBRARY.md`.
