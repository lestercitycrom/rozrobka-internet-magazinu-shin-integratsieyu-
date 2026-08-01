# NDI product media audit - 2026-08-01

## Evidence

The official NDI bundle was inspected without copying its proprietary source files into the repository.

| Check | Result |
|---|---:|
| Tire catalog rows | 1082 |
| Rows with a non-empty image identifier | 621 |
| Rows whose displayed image link has an empty identifier | 461 |
| Distinct non-empty image identifiers | 34 |
| Wheel catalog rows | 502 |
| Wheel image URL field | not supplied |

The tire workbook's `PlainUrl` values use NDI's `files.ndias.com/Imagev2.aspx?id=<UUID>` image handler. A read-only request against one supplied non-empty identifier returned HTTP 200 and `image/png`. The same endpoint was verified to support HTTPS, so the application normalizes the supplied HTTP form to HTTPS before any fetch. No credentials, local paths, full source URL list, or product identifiers are recorded here.

## Delivery boundary

The storefront never hotlinks the supplier URL. On catalog import, only a validated NDI media-source candidate and its source-artifact provenance are stored. The separate `ndi:media:sync` command then:

- accepts only the exact HTTPS NDI image endpoint on the configured allowlist and a UUID identifier;
- disables redirects, uses bounded connection/response timeouts and a size cap;
- requires PNG, JPEG or WebP response headers and validates the actual image bytes, dimensions and pixel cap;
- saves a locally managed media file, deduplicated for supplier-managed content by SHA-256;
- uses the existing text/graphic fallback when no NDI image exists or download fails;
- does not replace a manually selected product primary image.

Failed downloads use bounded exponential retries; an invalid source, redirect, unexpected media type, corrupted file or exhausted retry budget remains a local fallback rather than a broken supplier link.

One NDI identifier may be associated with several product rows. The command downloads that binary once and records a source-provenance assignment for every product to which NDI supplied that same identifier; it never guesses a relationship or borrows an image from another product.

## Operational note

Existing catalog records predate the media-source schema. A full NDI catalog re-import with the updated parser version is needed once to create their candidates; it remains safe because downloading is outside the catalog database transaction and is idempotent. Customer-provided and manually uploaded media retain precedence.
