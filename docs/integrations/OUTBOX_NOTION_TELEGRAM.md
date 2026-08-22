# Notion and Telegram order notifications

Both integrations are disabled by default. A paid-order message is created only after authoritative Dintero capture/`paid`; authorization/reservation and NDI acceptance do not create it. No checkout request makes a Notion or Telegram call directly.

## What is needed from the customer

For Notion:

- an internal Notion integration token, shared with the destination database;
- the destination database ID;
- the name of its title property (normally `Name`).

For Telegram:

- a bot token created in BotFather;
- the target chat or channel ID;
- the bot must already be allowed to post in that chat/channel.

## Enabling

Set these only in the server environment, then restart the application workers:

```dotenv
OUTBOX_NOTION_ENABLED=true
OUTBOX_NOTION_TOKEN=secret
OUTBOX_NOTION_DATABASE_ID=database-id
OUTBOX_NOTION_TITLE_PROPERTY=Name

OUTBOX_TELEGRAM_ENABLED=true
OUTBOX_TELEGRAM_BOT_TOKEN=secret
OUTBOX_TELEGRAM_CHAT_ID=-1001234567890
```

Leaving any required value blank keeps that destination bound to its disabled adapter, even if its `*_ENABLED` flag was set accidentally.

## Data and delivery behaviour

The local outbox payload is encrypted and currently contains customer contact fields and product lines so an approved operational mapping can be added without changing the payment lifecycle. The current HTTP destinations deliberately send only the public reference, paid total/currency, line counts and stable delivery key; customer PII and free-text notes are not sent to Notion/Telegram until the owner approves the exact database schema and PII policy.

Paid payloads also carry `integration_profile` and `is_integration_test`. When Notion is enabled, its approved mapping must expose or filter this flag so sandbox captures can never be mistaken for commercial customer orders.

The outbox claim prevents concurrent workers. Delivery is recorded locally only after the remote provider acknowledges the request; HTTP failures are retried with backoff. This is intentionally **at-least-once** delivery: a process crash after remote success but before the local commit can create a duplicate. The stable delivery key is included in both destinations for manual reconciliation, but Telegram `sendMessage` has no idempotency key and the current Notion adapter does not yet upsert by a unique database property. Do not enable either destination with a “no duplicates” acceptance promise until the Notion schema and an explicit Telegram duplicate policy have been approved and tested.
