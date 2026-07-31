# NDI official API reply — verified decisions

Date reviewed: 2026-07-31
Owner: Mister Red
Source: `API oppsett og data filer fra NDI Norge AS.pdf`

This note supersedes the unresolved questions in the initial API audit. It contains no credentials and no customer PII.

## Confirmed account and environment

- The test `PartyID` is `1220000`. The earlier value `122000` was missing one zero.
- `1220000` is a test customer identifier, not EasyTire's future production customer number.
- NDI asks us to use `https://salesapi.ndias.com/EndPoint` with the supplied test account because their separate test environment has problems.
- The supplied account does not effectuate orders and is safe for test traffic, but NDI must be notified before order testing because requests go to the production endpoint.
- NDI supplied test product `4765205551649102` and a C1 example using quantity `2`.

## Confirmed data ownership

- Browsing, search, filters, catalogue data, ordinary stock display and prices come from the files imported into the local database.
- Inquiry is not a browse-time API. It may be called only as confirmation after an explicit buyer action, for example adding a product to the cart or proceeding through checkout.
- Neither A2 nor C1 returns prices.
- `Net_Price` in the data files is NDI's invoice price to EasyTire before environmental fees and VAT.
- `Gross_Price` and `Net_Price` in the files are current source prices, but the final EasyTire B2C selling-price formula still requires an explicit commercial rule for environmental fees, VAT, markup and rounding.

## Confirmed order lifecycle

- NDI cannot reserve goods through this API.
- NDI orders are normally processed continuously.
- NDI orders cannot be cancelled through the API.
- NDI recommends C1 for a new integration.
- EasyTire's current agreement does not permit alternate delivery addresses. In C1 the delivery address should be omitted so NDI uses the standard contractual address.
- `Status_Request` is used for order status.
- `DesAdv_Request` is used for tracking/despatch data; tracking is available only after the order has been picked.
- NDI knows of no fixed inquiry/order-line limit, but this is not an authorization for catalogue-wide polling.

## Project decision

The integration is viable. The accepted flow is:

```text
NDI files -> local catalogue/prices/stock
buyer action -> Inquiry availability confirmation
payment authorization (no capture)
single idempotent C1 submission
accepted C1 -> payment capture
rejected C1 -> payment void
uncertain timeout -> manual review + Status_Request, never blind C1 retry
picked order -> DesAdv_Request for tracking
```

If the selected payment provider cannot separate authorization and capture, checkout must remain disabled until a documented manual-review/refund policy is approved.

## Remaining external inputs

- SFTP connection details and delivery schedule.
- EasyTire's production PartyID when NDI provisions the production account.
- Selling-price policy: environmental fees, VAT, markup and rounding.
- Payment provider and confirmation that authorization/capture/void are supported.
- Manual operational contact/procedure for cancelling an NDI order outside the API.
