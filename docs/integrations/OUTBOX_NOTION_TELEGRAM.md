# Notion and Telegram order notifications

Both integrations are disabled by default. They are invoked only by the existing outbox after an order has reached the paid state. No checkout request makes an external call directly.

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

The outgoing snapshot deliberately excludes the customer’s name, email, phone, address and free-text note. It contains only the public order reference, paid total/currency, number of item rows and the stable outbox delivery key.

The outbox claim prevents concurrent workers and records delivery before a second worker can send the same message. HTTP failures remain pending and are retried with the existing backoff. Do not enable a destination until its recipient has been checked: remote providers do not offer a cross-provider atomic acknowledgement with the local database.
