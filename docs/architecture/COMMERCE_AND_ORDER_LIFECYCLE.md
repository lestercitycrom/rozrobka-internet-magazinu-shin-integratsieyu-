# Commerce and order lifecycle

## Safety boundary

The browser never supplies a trusted price, title, stock value or total. A cart
stores only an external SKU and a bounded quantity. Each read rehydrates the
line from the public catalog and the active pricing policy. Checkout repeats the
same resolution inside a database transaction and freezes an immutable order
snapshot.

An order created before the NDI and payment contracts are confirmed is a local
order request. Its initial status is `pending_review`; payment state is
`not_started` and NDI state is `not_submitted`. No external mutation is implied.

## Money

All commerce calculations use integer minor units. Supplier decimals are parsed
from strings, never binary floats. Markup uses basis points and half-up integer
rounding. The configured selling-price increment is rounded upward so a margin
cannot be reduced by presentation rounding.

## Pricing

Selling a product requires at least one deterministic active pricing rule. Rules are
ordered by priority and specificity (`sku`, `brand`, `product_type`, `global`).
Each rule identifies the supplier price-list code, currency and an
explicit source tax mode (`gross` or `net`). Existing rules migrate as `gross`
for backward compatibility. The server loads both source columns and applies a
rule only to its exact selected mode; it never silently treats Gross as Net (or
vice versa). If that explicit source is absent, the next explicit matching rule
may act as a fallback. A present but stale/malformed source fails closed instead
of bypassing a pricing error. At equal priority and specificity Gross is
checked before Net, which allows an administrator to configure “recommended
Gross first, marked-up Net only when Gross is unavailable”. If no explicit rule
has a usable source, the item is unavailable for cart/checkout.

No default production rule is seeded because the commercial meaning of the NDI
price columns, VAT treatment and markup are not confirmed. Selecting Net does
not add VAT, environmental fees or any other inferred formula automatically.

## Idempotency

The checkout form receives a random idempotency key stored in the session. The
key is unique on orders. Repeating the same submission returns the existing
order instead of creating a duplicate. The key is rotated only after successful
creation.

## State machine

Allowed local transitions are explicit and append an `order_status_history`
row. Supplier and payment states are separate from the customer-facing order
status. Future adapters must use their own external idempotency reference and
may not blindly retry an uncertain external result.

## External adapters

External HTTP calls never run inside the transaction that creates an order. The selected payment provider is Dintero Checkout. `PrepareOrderPayment` performs readiness and a fresh NDI A2 before creating a manual-capture Dintero session. A verified authorization is followed by readiness, another current A2 and one guarded NDI C1 submission. NDI acceptance keeps the authorization open; it does not capture. An operations user records that the goods are physically received and ready before `CaptureAuthorizedPayment` may perform a final capture. Rejection voids the authorization; an uncertain C1 or payment result is persisted for reconciliation and is never blindly retried.

Notion and Telegram consume committed paid-order outbox messages only after actual capture. Their credentials remain disabled by default and are independent from the customer checkout transaction. See `DINTERO_PAYMENT_LIFECYCLE.md`.
