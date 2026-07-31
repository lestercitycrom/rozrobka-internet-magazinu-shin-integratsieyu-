# WHITE-021: схема raw/import audit NDI

Исполнитель: Mister White  
Модель: Codex Terra medium  
Статус: done, принят Mister Red после исправления уникальности manifest-link

## Результат

Созданы MySQL/SQLite-совместимые таблицы `suppliers`, `import_runs`, `source_artifacts`, `import_run_artifacts`, `import_rejections` и тесты ограничений.

## Проверено

- raw-артефакт уникален по поставщику и SHA-256;
- один raw-файл можно переобработать в новом run;
- один run не может дважды связать тот же artifact и dataset role;
- аудитные внешние ключи не удаляются каскадно;
- индексы поддерживают журнал запусков и артефактов.
