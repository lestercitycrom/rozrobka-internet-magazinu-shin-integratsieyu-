# NDI A2 read-only Inquiry boundary

`App\Modules\NdiApi\Contracts\NdiInquiryClient` is the only application boundary for an NDI availability check. The HTTP implementation serializes `inquiry_A2`, requires the confirmed `http://www.reifen.net` namespace, posts only to HTTPS, and parses `quote_A2` into typed result values.

## Safety defaults

- `NDI_INQUIRY_ENABLED=false` by default; CI and ordinary local runs cannot reach NDI.
- endpoint, Basic credentials, `PartyID`, and `AgencyCode` are environment-only configuration;
- credentials and response bodies are not placed in exception messages;
- XML parsing rejects DTDs and disables network access;
- leading-zero supplier SKUs remain strings;
- `ErrorCode=915` and other NDI business errors are returned as quote results, not confused with HTTP/transport failures;
- this module has no order-placement operation.

The current verified response supplies availability and delivery date but no price. Price policy remains a separate local domain concern and must not infer a price from the Inquiry result.

## Test layers

Synthetic redacted fixtures cover success and the observed PartyID error. HTTP tests use Laravel's fake transport and reject stray requests. A staging read-only smoke check may be enabled only after NDI confirms the correct EasyTire `PartyID`; it must use a known safe SKU and must never be promoted to an automatic production test.
