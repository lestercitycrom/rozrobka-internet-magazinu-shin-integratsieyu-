# NDI C1 order, status and despatch boundary

## Safety invariant

NDI order submission is an external mutation and is disabled by default. Tests use fake HTTP only. No CI, local seed, browser smoke test, scheduler or health check may submit a C1 order.

The order client must require all of the following before it can send:

- an explicit `NDI_ORDER_ENABLED=true` switch;
- HTTPS endpoint and environment-only Basic credentials;
- configured PartyID and AgencyCode;
- a unique local customer reference already persisted on the local order;
- a completed action-time Inquiry for the same SKU quantities;
- an authorized, not captured, payment attempt;
- no prior accepted, rejected or uncertain C1 attempt for that reference.

The test PartyID and test SKU are public contract examples, not source-code defaults. Credentials are never committed.

## C1 request profile

- root: `order` in `http://www.reifen.net`;
- `DocumentID`: `C1`;
- `TransportPriority`: `REPL`;
- article identifier: `BuyersArticleID`;
- standard contractual delivery: omit `Consignee`;
- alternate consignee data stays unsupported until NDI confirms a changed agreement;
- line IDs are unique strings and quantities are positive integers.

## Idempotency and ambiguous responses

The unique customer reference is the business idempotency key. C1 must not be retried automatically after a connection timeout or an unreadable response: the first request may already have created the NDI order. Such an attempt is marked `unknown` and payment is not captured. `Status_Request` needs an NDI order reference, so an outcome with no returned NDI reference must be reconciled with NDI support using the EasyTire customer reference.

A successful HTTP response is not enough. The XML root, namespace, header `ErrorCode`, every line error, external order reference and confirmed quantity must be parsed and validated before the order is marked accepted.

## Status and tracking

- `Status_Request` receives the NDI order reference and returns sales/document status. It is not an order creation or cancellation operation.
- `DesAdv_Request` receives the NDI order reference and returns despatch/tracking details after picking.
- Raw external status text is preserved for audit, then mapped conservatively. Unknown values remain `unknown`; they are never guessed into a terminal local status.
- Tracking URLs are treated as untrusted external URLs and rendered only after scheme/host validation at the presentation boundary.

## Payment ordering

```text
local order -> current price validation -> Inquiry
-> authorize payment -> submit C1 once
-> C1 accepted: capture -> paid/processing
-> C1 rejected: void -> rejected/manual review
-> C1 uncertain: no capture, no retry -> manual review/status reconciliation
```

Because NDI has neither reservation nor API cancellation, capture-before-C1 is not an acceptable default.

## Test policy

- Codec and client tests use synthetic fixtures and Laravel HTTP fakes.
- A live Inquiry is read-only and can be run manually with the configured test account.
- A live C1 test uses only the NDI-supplied test account/product and only after NDI receives the prepared notice.
- The live C1 command must require an interactive acknowledgement or a one-time explicit reference; it is never part of automated test suites.
