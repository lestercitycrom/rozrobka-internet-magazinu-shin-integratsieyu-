# RED-040 — NDI C1, Status and DesAdv boundaries

Owner: Mister Red
Priority: P0
Status: active

## Scope

- Record NDI's official reply and replace earlier assumptions.
- Implement strict typed XML codecs for C1 order, Status_Request and DesAdv_Request.
- Add fake-HTTP contract tests with synthetic, secret-free fixtures.
- Keep all mutating traffic disabled by default.
- Repeat one read-only Inquiry with PartyID `1220000` when credentials are available.
- Send one C1 test only after NDI has received the prepared notice.

## Acceptance

- No catalogue/browse-time NDI calls.
- No reserve or API-cancel capability is exposed.
- C1 cannot be blindly retried after an ambiguous response.
- Standard delivery request omits alternate Consignee.
- Status and tracking are separate read operations.
- Live credentials, raw responses and customer PII are absent from git and logs.
- Unit/feature tests pass before any live C1 request.
