# NDI media completeness audit — 2026-08-01

## Result

The official NDI files currently contain enough information to assign product images to only part of the catalogue:

| Catalogue rows | Rows with an explicit NDI image UUID | Rows without an image UUID |
|---|---:|---:|
| Tires | 1,082 | 621 | 461 |
| Wheels | 502 | 0 | 502 |
| Total | 1,584 | 621 | 963 |

The 621 tire assignments refer to 34 distinct official PNG files. Every one of those 34 URLs returned HTTPS 200, passed MIME and image decoding checks, was downloaded to local storage, and was assigned to the corresponding products. Reuse is intentional: one image can represent several dimensions of the same brand and model. No supplied UUID was observed crossing a brand/model boundary.

## Why the remaining rows are not auto-filled

- The 461 missing tire rows contain an empty image identifier in the official workbook.
- The wheel workbook contains no image UUID, URL, or other media field.
- The supplied ZIP, PDF, XML examples and accompanying documents contain no additional product-image feed.
- Exact EAN and item-number matching does not recover any missing image.
- Matching only by brand/model or technical dimensions would be an inference and can show a visually different tread, finish, colour, drilling, or product generation.

The storefront therefore uses a clearly labelled, code-generated product-type fallback for missing images. It does not hotlink third-party images or present an inferred image as the actual product. An administrator can upload and assign an approved image manually; a manual primary image remains preferred over a supplier-managed image.

## Input still required

Ask NDI for:

1. the wheel-image feed or image identifiers for the 502 wheel rows;
2. the missing image identifiers for the 461 tire rows;
3. confirmation whether one supplied image may be inherited by every size of the same brand/model;
4. confirmation that the downloaded product images may be stored locally and displayed in the EasyTire webshop.

Until those answers or customer-approved assets arrive, 963 catalogue rows will intentionally keep the honest fallback.
