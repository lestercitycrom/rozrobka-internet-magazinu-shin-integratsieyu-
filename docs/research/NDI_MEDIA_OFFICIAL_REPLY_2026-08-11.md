# NDI product-media reply - verified decisions

Date reviewed: 2026-08-11  
Owner: Mister Red  
Source: `API oppsett og data filer fra NDI Norge AS 2.pdf`

## Confirmed by NDI Norge

- Missing `PlainURL` values are expected because NDI's marketing team attaches media manually and continuously.
- NDI has added `PlainURL` to the wheel file, although the updated workbook itself was not attached to the received PDF or found among the accompanying downloads.
- NDI does not use EDIWheel MktCat. `PlainURL` is currently the only offered media source.
- EasyTire may fill missing tire media from another row with the same manufacturer and tread pattern.
- EasyTire may fill missing wheel media from another row with the same manufacturer and model.
- NDI states that every product with the same manufacturer and tread pattern uses the same image.
- NDI has the right to use the images and may pass marketing usage rights to EasyTire. NDI stated no other usage restriction.
- A `PlainURL` may be corrected if NDI discovers an error. The URLs have no separate versioning contract.
- NDI is working on new endpoints but supplied no ETA.

## Application decision

Media inheritance is allowed only when all of these conditions hold:

1. Product type is identical.
2. Manufacturer is identical.
3. Normalized exact model/pattern is identical; no fuzzy or partial matching is used.
4. The exact group contains one unique explicit official NDI URL.
5. A conflicting group with more than one official URL is not inherited.

Inherited sources keep separate provenance (`ndi_catalog_inherited`). A manually assigned administrator image remains primary. Removing or changing a donor is reconciled without leaving an untraceable supplier assignment.

The importer accepts the legacy wheel schema and strict updated variants ending in `PlainUrl` or `PlainURL`. Unknown non-empty media URLs still quarantine the row instead of being silently trusted.

## Verified staging impact before publication

| Metric | Count |
|---|---:|
| Catalog products | 1,584 |
| Explicit NDI media sources | 621 |
| Exact eligible inherited tire targets | 346 |
| Eligible exact groups | 19 |
| Ambiguous targets/groups | 0 / 0 |
| Expected explicit + inherited coverage | 967 |

All 346 eligible targets are currently published products. The existing wheel delivery contains no explicit media source, so wheel coverage remains dependent on receipt and import of NDI's updated wheel workbook.

## External follow-up

Request the actual updated wheel workbook containing `PlainURL`. No further permission question is required for exact manufacturer + model/pattern inheritance under the current NDI reply.
