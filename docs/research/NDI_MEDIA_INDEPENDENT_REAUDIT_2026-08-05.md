# NDI product-media independent re-audit — 2026-08-05

## Verdict

Two independent auditors and the project lead checked the raw delivery, importer, database, local storage, public URLs and browser rendering.

| Catalogue | Rows | Explicit valid NDI image UUID | No explicit image UUID |
|---|---:|---:|---:|
| Tires | 1,082 | 621 | 461 |
| Wheels | 502 | 0 | 502 |
| Total | 1,584 | 621 | 963 |

The current application does not lose any image explicitly supplied by NDI. On staging, all 621 rows with a valid UUID have a source record, a downloaded asset, a primary assignment, a hash-valid local file and a working same-origin storefront URL. The 621 assignments resolve to 34 distinct PNG files. There are no pending or failed current sources, missing files, hash mismatches, orphan records or CSP/browser failures.

The 963 remaining catalogue rows cannot be assigned a product image strictly from the delivered package:

- 461 tire rows contain `PlainUrl` with an empty `id=`; that endpoint returns a 1×1 PNG, not a product photograph;
- the 502-row wheel workbook has no image, media, EAN or manufacturer-article field;
- the stock workbook, XML examples, DOCX and six protocol PDFs contain no further product-media mapping or endpoint;
- there are no hidden sheets, rows, columns, drawings, embedded media, hyperlinks, external connections or custom XML in the three workbooks.

## Strong inferences that remain unsafe without supplier confirmation

Of the 461 unpictured tire rows:

- 346 share an exact `Brand + Mønster` value with rows that all point to one unique image UUID;
- 115 belong to nine models that have no image at all;
- exact EAN matching recovers no image;
- two repeated `Supplier_itemID` values cross special variants (`used 8 mm` and `avm.`), so they are not safe automatic image joins.

The 346 model-level matches are a strong indication that NDI images are design/tread-pattern media rather than size-specific photography. They remain an inference because the delivery contains no stable design ID or explicit permission to inherit an image across variants.

## Likely missing official source

The official EDIWheel master-data documentation describes a separate **Marketing Catalogue (MktCat)** organised by tread pattern/design and containing tire pictures, graphics, marketing text and test results: [EDIWheel Masterdata — Marketing Catalogue](https://ediwheel.net/overview/masterdata/). EDIWheel also lists Marketing Catalogue as a separate service component in its [pricing overview](https://ediwheel.net/pricing/).

MktCat, its schema, access details and join key were not included in NDI's delivery. It is the most plausible supported source for the missing tire design images and must be confirmed by NDI Norge.

A read-only check also found that an official NDI webshop can return a product image for at least one exact wheel article from the delivered wheel workbook (`730009519511445442`, Mega Wheels Pyxis), while that mapping is absent from the workbook. This proves that more media exists somewhere in NDI's commerce platform, but it does not make the public webshop a supported integration API.

NDI Norge's public site exposes its current [webshop and product catalogue](https://shop.ndi.no/). The supplied package contains no image licence. Public availability alone is not treated as permission to scrape, hotlink, cache, transform or commercially display media; written supplier permission is required.

## Internal defects found and corrected

The first system audit found latent defects that did not cause the current 963 gaps but could hide valid images later. They were corrected before the support request was prepared:

1. Failed image URLs no longer stop forever after five attempts. Exponential retry now continues with a capped interval, and `--force` provides an immediate operational retry.
2. A new source artifact resets a failed source even if its URL is unchanged.
3. Downloaded sources are reconciled: missing/corrupt local files are redownloaded and missing supplier assignments are restored.
4. An administrator's deliberate removal is stored as explicit suppression, so reconciliation does not undo it.
5. An unknown non-empty NDI media URL now quarantines the workbook instead of silently becoming “no image” and removing an existing assignment. The known empty `id=` marker remains valid absence.
6. Stable UUID URLs are periodically refreshed after seven days, preserving the currently valid image until replacement bytes pass validation.
7. Media failures and pending counts are visible in system health; a failed media download returns a failing command status.
8. The media command lock was extended beyond the maximum normal batch timeout.
9. Re-uploading a duplicate old bundle no longer makes stale NDI catalogue data appear fresh.

## Remaining operational dependency

The application schedules media reconciliation hourly, but it cannot fetch a new catalogue bundle until NDI provides the supported SFTP/API location, stable file names, cadence and access method. Current completeness is proven against the last received bundle, not against an unpublished newer supplier feed.

## Verification after corrections

- Full PHP suite: 262 passed, 1,979 assertions.
- Production frontend build: passed.
- Playwright: 24 passed, 2 intentionally skipped across buyer desktop/mobile, private Russian preview and administrator desktop/mobile projects.
- Targeted media/import/admin-health suite: passed, including permanent-retry, forced retry, corrupt downloaded file repair, missing assignment repair, explicit suppression, URL refresh, changed supplier URL validation and non-zero failure reporting.

## Supplier information still required

The prepared Norwegian/Russian follow-up asks NDI for MktCat or an equivalent media feed, stable tire and wheel join keys, interpretation of empty image IDs, permission for pattern-level inheritance, written media rights, update/deletion semantics, and the SFTP/full-delta schedule required for automated imports.
