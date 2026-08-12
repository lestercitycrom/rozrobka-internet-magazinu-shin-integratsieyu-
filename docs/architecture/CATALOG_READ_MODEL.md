# Public catalog read model

`App\Modules\Catalog\ReadModel\CatalogReadService` is the only public-catalog query boundary. It returns SKU-level `CatalogItem` records, so the value used for a later supplier inquiry remains exact and string-preserved.

## Contract

- `search(CatalogQuery $query): CatalogPage`
- `findPublicSku(string $sku): ?CatalogItem`

`CatalogQuery` accepts product type, exact brand, text/SKU search, tire size/season filters, wheel dimensions/fitment filters, and `page`/`perPage` (bounded to 1–100). A `CatalogItem` contains identity/title/specification data, current Gross and Net source-price records (`amount`, `currency`, `taxMode`, `observedAt`) for server-side pricing, and current national stock (`quantity`, `observedAt`). Supplier source prices are not rendered to customers; only the price derived from an explicit pricing rule is public. The read model intentionally does not calculate a selling price, infer a currency, tax or fee formula, or promise availability/delivery.

Both methods always require a published product, active supplier, and `catalog` SKU state. Draft/hidden products and `stock_only`, `needs_enrichment`, `missing`, and `inactive` SKUs never enter the public read model.

The service loads a page with a bounded query count: page count + page rows + one batched gross-price query + one batched active-warehouse stock query. It does not query one price or stock row per item. NDI's `InStockNorway` record is a national summary capped at 40 and therefore takes precedence over the sum of physical locations; the locations are summed only as a fallback when the national row is absent.

`CatalogDemoSeeder` is deterministic and explicitly rejects non-`local`/`testing` environments. It provides one published tire with a leading-zero SKU and stock, without creating price data or assuming a currency.
