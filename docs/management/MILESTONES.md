# EasyTire — этапы и фактический статус

Владелец плана: Mister Red. Обновлено: 2026-08-01.

Этот файл описывает фактическое состояние, а не обещания. Подробный срез — [CURRENT_PROJECT_STATUS_2026-08-01.md](CURRENT_PROJECT_STATUS_2026-08-01.md).

## M0 — фундамент и поставка: выполнен

- Laravel, собственная админка, NB/RU internal preview, Pest и Playwright;
- автоматическая staging-поставка, HTTPS, health-check и базовые operational checks;
- без готового admin framework.

## M1 — каталог и данные NDI: выполнен для безопасного staging-каталога

- официальный пакет из трёх файлов валидирован, импорт идемпотентен и сохраняет raw-artifacts;
- 1 584 товара: 1 082 шины и 502 диска; импорт не означает автоматическую коммерческую публикацию;
- фильтры, поиск, автодополнение, карточки, admin media/import controls готовы;
- фотографии: 621 назначение от NDI, 34 уникальных локально сохранённых файла, 963 честных fallback. Полнота зависит от недостающего фида/прав на изображения.

Доказательства: [валидация пакета](../research/NDI_OFFICIAL_DATA_BUNDLE_VALIDATION_2026-07-31.md), [аудит медиа](../research/NDI_MEDIA_COMPLETENESS_2026-08-01.md).

## M2 — подбор и сервисы: реализовано внутреннее ядро, внешний запуск заблокирован

- доступны каталоговый подбор, запрос услуги, CMS-страницы услуг и русская тестовая локаль;
- Statens-адаптер существует, но фактический запрос не включается без ключа и одобрения;
- сервисные цены, правила доступности, реальные слоты и публичный legal/contact copy не утверждены.

## M3 — корзина, заказ, оплата и допуслуги: Dintero sandbox в интеграции

- реализованы локальная корзина, checkout guard, заказ, Dintero manual-capture session/callback/reconcile/capture/void, идемпотентные payment boundaries и outbox;
- NDI A2/C1 включён в безопасную последовательность authorization → repeated readiness/A2 → C1 → operator goods-ready → capture;
- Notion/Telegram paid-order events создаются только после capture и остаются disabled-by-default до получения схемы/доступов;
- test credentials Dintero получены; production KYC, сроки резервов, cancellation/refund rules, NDI production acceptance и коммерческие правила ещё не закрыты.

Ни оплату, ни допуслуги нельзя считать завершёнными или включать в production до отдельных acceptance-проверок.

## M4 — production release: не начат

Production gate требует закрыть внешние входные данные, провести финальный regression/security/accessibility/performance прогон, настроить off-host backup и выполнить owner acceptance. См. [release checklist](../operations/RELEASE_READINESS.md).
