# NDI XLSX fixtures

These workbooks are synthetic, redacted test inputs for the NDI catalog importer. They contain no production SKU, price, customer number, credential, address, or other PII. Synthetic identifiers and free-text values use the `TEST-` marker, except for the deliberately numeric leading-zero test SKU `0001234567890`; URLs use the reserved `example.invalid` domain.

## Workbook contract

- Every workbook has exactly one sheet named `Article`.
- Row 1 is the source header; data starts at row 2.
- Blank optional cells are real blank cells, not the strings `NULL` or `null`.
- The workbooks contain no formulas.
- Identifier cells are text. `Gross_Price`, `Net_Price`, `Weight`, `Brutto`, `Netto`, and all stock quantities are numeric cells, matching the observed source value types.

### Tire header (`Export.xlsx` shape)

Exactly 34 columns, in this order:

```text
ItemID|ItemDesc|Brand|Mønster|Width_mm|Widht_inches|Profile|Rim_width|Load_index|Speed_index|Reinforced|Construction|Type|Gross_Price|Net_Price|Rolling_resistance|SEASON|Sound|Sound_class|Wet_grip|XINFORM|WINTERTYRE|External_EAN|Supplier_itemID|Weight|Enviremental_fee|3PMSF|Ice_grip|Eprel_link|EU_tyreclass|EU_label_regulative|Run_flat|PlainUrl|Production_year
```

Types: `ItemID` and the size/label/identifier attributes are text; `Gross_Price`, `Net_Price`, and `Weight` are numbers; optional fields may be blank.

The source spellings `Mønster`, `Widht_inches`, and `Enviremental_fee` are intentionally preserved.

### Wheel header (`Export_1.xlsx` shape)

Exactly 14 columns, in this order:

```text
Varenummer|Beskrivelse|Fabrikat|Modell|Bredde|Diameter|Bolthull|Boltsirkel|ET|Navåpning|Boring bolthull|GFARVEPOL|Brutto|Netto
```

Types: the first 12 columns are text; `Brutto` and `Netto` are numbers.

### Stock header (`Export_2.xlsx` shape)

Exactly 8 columns, in this order:

```text
ItemID|InStockNorway|InStockVestby|InStockKristiansand|InStockTrondheim|InStockTromso|InStockBergen|InStockKopervik
```

Types: `ItemID` is text; all seven stock columns are integer numbers. `InStockNorway` is the national availability indicator. In the supplied NDI feed it equals the sum of the six location columns capped at `40`.

## Exact fixture expectations

### `tires_valid.xlsx`

- 3 data rows, 34 columns, 3 unique `ItemID` values.
- Expected accepted rows: 3; expected rejected rows: 0.
- Expected normalized products/SKUs: 3 tire products and 3 supplier SKUs.
- Expected source prices: 3 gross plus 3 net numeric values.
- `0001234567890` must round-trip as the exact text value shown. It is deliberately numeric-looking but stored as a text cell; the two leading zeroes must not be stripped and the value must not be converted to a number or scientific notation.
- `TEST-TIRE-003` exercises inch width: `Width_mm` is blank and `Widht_inches` is the text value `12.5`.
- Rows include both populated and blank optional label/EAN/EPREL/production-year fields.

### `wheels_valid.xlsx`

- 3 data rows, 14 columns, 3 unique `Varenummer` values.
- Expected accepted rows: 3; expected rejected rows: 0.
- Expected normalized products/SKUs: 3 wheel products and 3 supplier SKUs.
- Expected source prices: 3 gross plus 3 net numeric values.
- `TEST-WHEEL-003` has numeric `Brutto=0` and `Netto=0`. Zero is valid input data and must not be parsed as blank; the publication/pricing policy may keep that SKU unpublished.

### `stock_all_warehouses.xlsx`

- 7 data rows, 8 columns, 7 unique `ItemID` values.
- Expected accepted rows: 7; expected rejected rows: 0.
- Expected stock observations: 49 (`7 SKU × 7 stock columns`), including zero observations.
- The six catalog SKUs from `tires_valid.xlsx` and `wheels_valid.xlsx` are present.
- The stock row for `0001234567890` must join to the tire SKU by the exact 13-character text key, including both leading zeroes.
- `TEST-STOCK-ONLY-001` is the only stock-only SKU. Expected catalog state: `stock_only`/quarantined and not published.
- `TEST-WHEEL-003` is the explicit all-zero-stock row.
- Every named location column is exercised with a positive value somewhere in the fixture; zero quantities are also present.
- The fixture includes a row whose physical-location sum exceeds 40. For every row, `InStockNorway = min(location sum, 40)`, matching all 2,304 rows in the supplied NDI stock workbook.

### `duplicate_sku.xlsx`

- 2 data rows with the same text key `ItemID=TEST-00000000000001` at Excel rows 2 and 3.
- Expected result: one duplicate-key violation covering rows 2 and 3; accepted/upserted rows: 0.
- The import run must not publish or change the current catalog state.

### `invalid_decimal.xlsx`

- 1 tire-shaped data row.
- Excel row 2, field `Net_Price`, contains the text value `TEST-NOT-A-DECIMAL`; `Gross_Price=150` remains numeric.
- Expected result: exactly 1 rejected row, identifying Excel row 2 and field `Net_Price`; accepted/upserted rows: 0.
- The import run must not publish or change current prices/catalog state.

### `negative_stock.xlsx`

- 1 stock-shaped data row.
- Excel row 2, field `InStockNorway`, contains numeric `-1`; all six location values are numeric zero.
- Expected result: exactly 1 rejected row, identifying Excel row 2 and field `InStockNorway`; accepted/upserted rows: 0.
- The import run must not replace or change current stock.

## Safety assertion

Tests may additionally scan every text cell and fail if it contains a production NDI host, an unapproved numeric-only SKU other than the explicit fixture value `0001234567890`, customer/party ID, email, phone number, credential, or non-reserved external URL.
