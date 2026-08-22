# EasyTire / shop-ndi

Проект интернет-магазина шин и сервисных услуг EasyTire для Tromsø.

Текущий этап: рабочий staging-магазин, NDI/Dintero sandbox acceptance и подготовка production-gates.

## Рабочий процесс

GitHub является основным шлюзом кода и review. Перед новой задачей читать:

1. `project-control/STATUS.md`;
2. `project-control/DECISIONS.md`;
3. `project-control/WORKFLOW.md`;
4. `project-control/CONTEXT.md`;
5. `project-control/CURRENT_TASK.md` и связанный GitHub Issue.

Актуальные объединённые требования находятся в `docs/management/CURRENT_TZ_2026-08-22.md`. Старые документы, ZIP и экспорты переписки используются как первичные evidence только по ссылке из активного Issue.

## Навигация

- Исходные материалы заказчика: `docs/primary/`
- Паспорт проекта: `docs/project/PROJECT_BRIEF.md`
- Аудит NDI API: `docs/research/NDI_API_AUDIT_2026-07-31.md`
- Предупреждение о секретах в исходном скриншоте: `docs/project/SECURITY_NOTE.md`
- Архитектурные решения: `docs/decisions/`
- Управление командой и backlog: `docs/management/`
- Актуальный workflow и runtime-status: `project-control/`
- Laravel-приложение: `application/`

## Ответственные

- Владелец — бизнес-решения и отдельное разрешение production-gates.
- ChatGPT Pro / PM — постановка Issues и независимая приёмка точного PR head SHA.
- Mister Red / Codex — главный программист, архитектура, реализация, тесты и evidence; собственный PR не принимает.
- Дополнительные инженеры и аудиторы — только ограниченные подзадачи внутри разрешённого Issue.

## Принятый стек

- Laravel 13, PHP 8.3+;
- MySQL 8 и Redis 7 в production;
- собственная административная панель на Laravel/Blade/Tailwind без готового admin framework;
- Blade/Tailwind для серверной витрины без отдельного SPA;
- Pest для unit/feature/integration-тестов;
- Playwright для сквозных сценариев покупателя и администратора.

## Текущие ограничения

Staging использует отдельные Dintero/NDI sandbox-gates. Коммерческий NDI C1, production payment, Notion/Telegram и другие внешние действия не включаются автоматически после merge. Точное состояние хранится в `project-control/STATUS.md`.

Секреты нельзя хранить в документации или коммитить в репозиторий. Исходный скриншот в `docs/primary` содержит тестовые credentials NDI и не должен публиковаться без редактирования. В будущем секреты должны передаваться только через переменные окружения/secret storage.
