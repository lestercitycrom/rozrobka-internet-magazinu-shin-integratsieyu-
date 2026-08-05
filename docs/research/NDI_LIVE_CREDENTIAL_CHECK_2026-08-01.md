# NDI credential check — 2026-08-01

> Superseded on 2026-08-05 by `NDI_ACCESS_RECHECK_2026-08-05.md`. NDI supplied a
> different credential pair, and the exact read-only example then succeeded.

Owner: Mister Red
Environment: isolated EasyTire staging

## Safe check performed

- Endpoint: official HTTPS sales endpoint from NDI's reply.
- Operation: read-only A2 Inquiry only.
- Article: NDI's explicit test article `4765205551649102`.
- Quantity: `2`.
- PartyID: NDI's test identifier `1220000`.
- C1 orders, fulfilment reads and every other external mutation remained disabled.

## Result

The endpoint returned HTTP `401` during Basic authentication. The request did not reach PartyID, article or availability validation. The credential values loaded by Laravel had the same lengths as the supplied values, so the result is not explained by truncation during staging configuration.

The supplied pair may belong to another NDI service, may not yet be enabled for the sales endpoint, or may have been replaced. NDI must confirm the Basic-auth username/password intended for `https://salesapi.ndias.com/EndPoint`.

## Safety decision

`NDI_INQUIRY_ENABLED` was returned to `false` after this explicit smoke check. Credentials remain environment-only. `NDI_ORDER_ENABLED`, `NDI_C1_TEST_MODE` and `NDI_FULFILMENT_READ_ENABLED` remain `false`.

This does not block the local catalogue: official NDI files, source prices and stock data are already imported. It blocks only real-time availability/delivery confirmation until NDI corrects or enables the account.
