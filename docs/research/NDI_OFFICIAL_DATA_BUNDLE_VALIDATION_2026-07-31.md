# NDI official data bundle validation - 2026-07-31

## Evidence boundary

The official source archive `apioppsettogdatafilerfrandinorgeas.zip` was validated in staging. Proprietary XLSX files are not copied into this repository. No credentials or private filesystem paths are recorded here.

## Source bundle

| Dataset | Source rows | SHA-256 |
|---|---:|---|
| Tires | 1082 | `0668eb81357daae0705bfb9e585033fd4dbfdbd6cbc82681ba6b3b6966bab0df` |
| Wheels | 502 | `d843fd7026fff3425f6488510fb6dbad0b6c4a660d1a96ea54bb20d7c3818c89` |
| Stock | 2304 | `aeeb3c8234903218ffed2c4c11e21773f3a248cb08270f7bf4ac6c24f5926439` |

The immutable raw-artifact verification result was `true`.

## Staging import outcomes

- Successful run: `01KYWHGREPMKZHVF7CK7GEDY70`
  - created: `25850`
  - rejected: `0`
- Repeated identical bundle: `01KYWHT79962YQKYWCTB603TSP`
  - outcome: `duplicate`

Two earlier failed runs are retained as diagnostic audit records only. They did not publish domain catalog data.

## Published-state verification

| Entity | Count |
|---|---:|
| Products | 1584 |
| Supplier SKUs | 2304 |
| Tire specs | 1082 |
| Wheel specs | 502 |
| Prices | 3168 |
| Warehouses | 7 |
| Current-stock rows | 16128 |

Products remain `draft`. This is intentional: a retail pricing policy has not yet been approved, so official supplier data must not imply a customer-facing retail price or publication decision.
