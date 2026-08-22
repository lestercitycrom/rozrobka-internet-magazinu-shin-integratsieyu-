# Dintero payment lifecycle

Updated: 2026-08-22. Owner: Mister Red.

## Fixed business decision

EasyTire uses Dintero Checkout. The customer authorizes the order amount at checkout; Dintero must not auto-capture it. EasyTire captures only after the ordered goods have physically arrived at EasyTire and an operator has confirmed that they are checked and ready for the agreed fitting or pickup.

The test and production paths use the same application code. Environment, account and credentials are server configuration only. Test account IDs must start with `T`; production account IDs must start with `P`. Secrets are never stored in Git or rendered in admin.

## Required sequence

```text
immutable order snapshot
  -> current price/stock/service readiness
  -> fresh NDI A2 Inquiry
  -> Dintero session (auto_capture=false)
  -> customer authorization/reservation
  -> authoritative Dintero transaction lookup
  -> readiness + fresh NDI A2 again
  -> one guarded NDI C1 submission
     -> NDI rejected: Dintero void
     -> NDI unknown/submitted: hold and reconcile, never blind-retry C1
     -> NDI accepted: keep authorization, do not capture
  -> EasyTire operator confirms goods received and ready
  -> final Dintero capture with the immutable item lines
  -> paid order outbox -> Notion / Telegram
```

The browser return URL is informational. Dintero callback plus a server-side `GET transaction` is authoritative. Callback requests are HMAC-validated when `DINTERO_REQUIRE_CALLBACK_SIGNATURE=true`. A paid/voided terminal local state cannot be downgraded by an older callback.

## Admin boundary

The order page can expose only state-compatible operations:

- reconcile an existing Dintero transaction;
- mark goods received/ready and capture only when payment is authorized and NDI is accepted;
- void only an uncaptured authorization while NDI is not submitted or has rejected the order.

The goods-ready action requires an operator note and records the operator and time. Generic local status changes do not call Dintero or NDI.

## Failure rules

- Price, stock, currency or immutable snapshot drift fails closed before authorization and again before C1.
- A definitive NDI rejection voids the authorization.
- An uncertain C1 result is not retried automatically and is not captured.
- An uncertain Dintero capture/void remains in an intermediate state and must be reconciled; the operator must not start a second payment.
- A provider-side unexpected capture is recorded, the local order is reconciled as paid, and paid outbox messages are created once. It is also marked as an operational anomaly.
- Notion and Telegram receive no order event for a pending or authorized payment. Their order event is created only for `paid`.

## Environment switch

The only deployment differences are environment variables:

```dotenv
PAYMENTS_ENABLED=true
PAYMENTS_PROVIDER=dintero
DINTERO_ENVIRONMENT=test
DINTERO_TEST_ACCOUNT_ID=T........
DINTERO_PRODUCTION_ACCOUNT_ID=P........
DINTERO_CLIENT_ID=...
DINTERO_CLIENT_SECRET=...
DINTERO_PROFILE_ID=default
DINTERO_CALLBACK_SECRET=...
DINTERO_REQUIRE_CALLBACK_SIGNATURE=true
```

For production, set `DINTERO_ENVIRONMENT=production` and replace the production account/client/signature values. No source-code branch or alternate checkout is allowed.

## External acceptance still required

- exact authorization lifetime for every enabled Dintero payment product and the recovery flow when NDI delivery exceeds it;
- Dintero production/KYC approval and a production Checkout client/profile;
- owner-approved cancellation, partial delivery, no-show, partial capture and refund policy;
- owner approval of the final Norwegian purchase terms;
- NDI production C1 permission and acceptance test.

Official implementation references:

- <https://docs.dintero.com/docs/checkout/quickstart>
- <https://docs.dintero.com/docs/checkout/create-session>
- <https://docs.dintero.com/docs/checkout/after-payment/>
- <https://docs.dintero.com/docs/checkout/transaction-management>
- <https://docs.dintero.com/docs/checkout/validating-callbacks/>
