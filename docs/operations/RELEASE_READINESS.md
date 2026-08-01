# Release readiness checklist

Статус на 2026-08-01: **не готово к production release**. Staging и внутренние тесты не заменяют production acceptance.

Текущая фактическая картина и внешние блокеры: [CURRENT_PROJECT_STATUS_2026-08-01.md](../management/CURRENT_PROJECT_STATUS_2026-08-01.md).

## Перед release

- Прогнать существующие Composer install, `npm ci`, production build, `php artisan test`, миграции и cache steps release hook.
- Проверить новые миграции на backup/restore. Hook выполняет `migrate --force --no-interaction` до атомарного переключения `current`.
- Не включать NDI A2/C1, payment, Notion, Telegram или Statens по умолчанию. Каждый флаг требует отдельного подтверждённого набора внешних данных и smoke test.
- Подтвердить утверждённые NB legal/contact/service тексты и права на изображения/поставочный фид.
- Не включать исходные файлы NDI, credential values, PII или webhook payloads в repository, release notes или логи.

## Внешние gates

- NDI: исправленные Basic credentials для sales endpoint, production PartyID, SFTP/schedule, уведомление и письменное разрешение C1; см. [credential check](../research/NDI_LIVE_CREDENTIAL_CHECK_2026-08-01.md) и [official reply](../research/NDI_API_OFFICIAL_REPLY_2026-07-31.md).
- Коммерция: утверждённые VAT/environmental fee/markup/rounding и service/add-on pricing rules.
- Оплата: выбранный provider с подтверждёнными authorization, capture и void/refund policy.
- Operations: off-host encrypted backup destination и проверенный restore.

## После deployment

- Запустить redacted `php artisan easytire:health`.
- Проверить scheduler и queue worker, pending/failed outbox, public routes и admin login.
- Для supplier-media release: `NDI_PARSER_VERSION=1.1.0`, повторный импорт официального пакета и `php artisan ndi:media:sync --limit=50`; записать counts downloaded/failed.
- Проверить `/robots.txt`, `/sitemap.xml`, `/`, `/dekk`, `/tjenester`, `/admin/login`, authorized `/admin`, `/admin/orders`, `/admin/health`.

## Rollback и backup

- До переключения должна существовать encrypted backup и проверенный restore на независимой off-host площадке.
- При сбое вернуть предыдущий атомарный release по deploy-механизму; не делать destructive schema rollback без согласованного recovery plan.
- После rollback перепроверить health, admin auth, queue, scheduler и pending/failed outbox.
