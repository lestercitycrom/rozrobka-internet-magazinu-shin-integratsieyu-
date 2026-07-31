# RED-030 — Commerce core

Owner: Mister Red
Status: done

## Delivered

- integer-minor-unit money and explicit pricing rules;
- SKU-and-quantity session cart rehydrated from the current catalogue;
- immutable checkout product/pricing snapshots;
- session-bound idempotency and safe duplicate submission handling;
- price/stock drift rejection before order creation;
- explicit order lifecycle with append-only history;
- disabled-by-default payment and NDI boundaries;
- NB storefront plus hidden RU buyer/admin preview;
- buyer and admin Pest/Playwright coverage, including a complete isolated checkout;
- no live payment, C1, Notion or Telegram side effect.

## External follow-up

Production activation still requires the final payment provider, commercial price/VAT policy, legal copy and the guarded NDI C1 test described in `RED-040`.
