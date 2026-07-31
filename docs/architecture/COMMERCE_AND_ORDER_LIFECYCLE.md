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

Selling a product requires one deterministic active pricing rule. Rules are
ordered by priority and specificity (`sku`, `brand`, `product_type`, `global`).
The selected rule identifies the supplier price-list code and currency. Missing
or ambiguous source prices make the item unavailable for cart/checkout.

No default production rule is seeded because the commercial meaning of the NDI
price columns, VAT treatment and markup are not confirmed.

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

NDI PlaceOrder, payment, Notion and Telegram adapters are not part of the local
transaction. They will consume committed outbox events only after their
contracts and credentials are confirmed. Until then they remain disabled and
the admin UI must show the order as requiring review.
