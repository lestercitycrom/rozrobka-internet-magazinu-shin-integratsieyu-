# EasyTire — текущий статус на 2026-08-01

> Исторический срез 01.08. Актуальные требования и статус Dintero, Notion и двух выездных машин: [CURRENT_TZ_2026-08-22.md](CURRENT_TZ_2026-08-22.md).

## Подтверждённо сделано

- Staging-проект, автоматическая поставка, собственная лаконичная админка, NB storefront и закрытый RU preview; тесты покупателя и администратора заложены с начала проекта.
- Каталог NDI импортирован из официального пакета: 1 584 товара, из них 1 082 шины и 502 диска. Импорт повторяем и аудируем; подробности — [валидация официального пакета](../research/NDI_OFFICIAL_DATA_BUNDLE_VALIDATION_2026-07-31.md).
- Современный каталог: фильтры, поиск/автодополнение, сортировка, responsive UI, стабильные hover states, product fallback и media library.
- По медиа подтверждено: **621/1 584** product-media assignments, **34** unique locally stored official files и **963** honest fallback. Fallback не маскируется под фото товара. Причины и безопасные ограничения — [NDI media completeness](../research/NDI_MEDIA_COMPLETENESS_2026-08-01.md).
- Service request forms, admin follow-up, CMS для информационных/юридических страниц, footer и checkout links, которые показываются только для опубликованного CMS-контента.
- Локальная корзина/заказ, order state boundaries, encrypted outbox, admin order audit и disabled-by-default Notion/Telegram adapters. Это не означает, что payment или add-ons готовы к запуску.

## Внутреннее состояние перед внешними интеграциями

- Все безопасно реализуемые без внешних секретов и коммерческих решений части завершены: каталог, фильтры и автодополнение, корзина, checkout foundation, заявки, допуслуги, custom admin, RU preview, payment boundary, NDI order boundary, encrypted outbox, health monitoring и автоматическое развёртывание.
- Допуслуги полностью реализованы в модели, админке, корзине и заказе, но исходно неактивны и без выдуманных цен. Их production-активация ожидает утверждённых заказчиком цен, применимости и операционных правил.
- Финальный объединённый локальный прогон: **234 PHP-теста / 1 676 assertions**, **14 Playwright-сценариев прошли / 2 неприменимых сценария пропущены**, Vite build, Blade/config/route cache, Pint и `git diff --check` прошли.
- До получения внешних данных дополнительных внутренних функций, которые можно честно включить в production, не осталось. После получения данных потребуется интеграционная настройка и повторный acceptance-прогон, а не переписывание основы магазина.

## Внешние блокеры и точные входные данные от заказчика/партнёров

### NDI

- Актуальные Basic-auth username/password именно для `https://salesapi.ndias.com/EndPoint`: безопасный read-only A2 smoke вернул HTTP 401 до проверки PartyID. Доказательство: [NDI live credential check](../research/NDI_LIVE_CREDENTIAL_CHECK_2026-08-01.md).
- Production PartyID после provisioning. Известный `1220000` — только тестовый PartyID; прежнее значение с одним нулём меньше неверно.
- Письменное разрешение/notice для C1 order testing, плюс операционный контакт и процедура отмены заказа вне API. До этого C1 и все mutation flags остаются выключены.
- SFTP host/port/user/authentication, каталог, формат, расписание и правила повторной доставки файлов.

### Коммерция, сервисы и оплата

- Формула B2C: VAT, environmental fees, markup, rounding, валюта и правила публикации. NDI prices — входные supplier prices, не готовые retail prices; см. [official API reply](../research/NDI_API_OFFICIAL_REPLY_2026-07-31.md).
- Service и add-on prices, доступность по районам/времени, правила dekkhotell, что считать заявкой/подтверждённым бронированием и кто обрабатывает обращения.
- Выбранный payment provider, аккаунт/ключи в защищённом server environment и подтверждение flows authorization → C1 → capture / rejection → void/refund. Не передавать секреты в чат или repository.

### Государственный подбор, контент и изображения

- Statens vegvesen API key и разрешённый способ/лимиты использования для vehicle lookup.
- Одобренные норвежские тексты: `personvern`, `kjopsvilkar`, `kontakt`, `om-oss` и все service pages. Черновики не должны публиковаться как юридически готовые.
- Права на изображения и дополнительный NDI media feed: identifiers для 461 шин без UUID и 502 дисков без media field, а также разрешение локально хранить и показывать NDI images. См. [media audit](../research/NDI_MEDIA_AUDIT_2026-08-01.md).

### Operations и уведомления

- Независимый off-host encrypted backup destination, retention и ответственный за restore test.
- Для Notion: internal integration token, database ID и имя title property; integration должна быть расшарена с этой database.
- Для Telegram: bot token и target chat/channel ID, а bot должен иметь право писать в него.
- Инструкции без секретов: [Notion/Telegram outbox](../integrations/OUTBOX_NOTION_TELEGRAM.md).

## Безопасные решения до получения данных

- NDI runtime A2/C1, payments, Statens, Notion и Telegram остаются disabled by default.
- Нет catalog-wide polling, бронирования stock или blind C1 retry.
- Нет выдуманных service prices, legal text, контактов, логотипов или товарных фото.
- Не публикуем источник/секреты/PII в документации и не отправляем их через чат.
