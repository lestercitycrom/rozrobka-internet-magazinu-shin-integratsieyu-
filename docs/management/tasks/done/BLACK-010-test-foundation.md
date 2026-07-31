# BLACK-010: тестовый фундамент

Исполнитель: Mister Black  
Модель: Codex Terra  
Статус: done, принят Mister Red

## Цель

Настроить Pest и Playwright с первого инкремента Laravel.

## Результат

- Pest unit/feature/architecture тесты;
- Playwright TypeScript с buyer desktop/mobile и admin desktop;
- зелёный smoke покупателя;
- явно отложенный admin smoke до появления `/admin/login`;
- воспроизводимые npm/composer команды.

## Критерии приёмки

Все доступные тесты проходят локально; браузерные тесты не используют внешние production-системы; Filament и готовые admin frameworks отсутствуют.
