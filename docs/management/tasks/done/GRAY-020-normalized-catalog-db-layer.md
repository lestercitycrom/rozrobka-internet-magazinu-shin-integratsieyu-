# GRAY-020: минимальный нормализованный DB-слой каталога NDI

Исполнитель: Mister Gray
Модель: Codex Terra high
Статус: done

## Цель

Подготовить SQLite/MySQL 8-совместимый слой данных, необходимый RED-010 для транзакционной публикации нормализованных шин, дисков, цен и складских остатков.

## Входные данные

- `docs/architecture/CATALOG_AND_NDI_DATA_MODEL.md`;
- принятая audit migration `2026_07_31_150000_create_ndi_import_audit_tables.php`;
- синтетические NDI fixtures из `application/tests/Fixtures/Ndi`.

## Границы и запреты

- только миграции, Eloquent models/enums/relations/casts и focused Pest tests;
- XLSX reader, import service, UI, admin и deploy не реализовывались;
- принятая audit migration не изменялась;
- `external_sku` хранится строкой, деньги — `decimal`;
- schema не использует несовместимые с MySQL 8 partial indexes или dialect-specific SQL.

## Результат

- добавлены нормализованные таблицы `brands`, `products`, `supplier_skus`, `tire_specs`, `tire_labels`, `wheel_specs`, `price_lists`, `sku_prices`, `sku_price_history`, `warehouses`, `stock_snapshots`, `current_stock`, `entity_changes`;
- current/history цены и остатка разделены, business uniqueness закреплена составными unique indexes;
- `supplier_skus.product_id` nullable для stock-only quarantine;
- добавлены Catalog enums и Eloquent models с отношениями и casts;
- добавлены focused schema/model tests.

## Критерии приёмки

- `(supplier_id, external_sku)` уникален, `0001234567890` сохраняется строкой с ведущими нулями;
- current price и current stock уникальны по составным business keys;
- history дедуплицируется по raw artifact и business key;
- stock-only SKU допускается без `product_id`;
- денежные значения возвращаются Eloquent как decimal string, а не float;
- связи и enum/datetime/boolean/integer casts проверены.

## Проверено

```text
php artisan test tests/Feature/Database/NormalizedCatalogSchemaTest.php tests/Feature/Database/NormalizedCatalogModelsTest.php
PASS: 6 tests, 47 assertions

php artisan test
PASS: 35 tests, 155 assertions

vendor/bin/pint --test
PASS
```

Runtime-проверки выполнены на тестовом SQLite. MySQL 8-совместимость обеспечивается переносимыми Laravel Schema Builder types/foreign keys/indexes; partial unique indexes и raw SQL не используются.

## Зависимости и открытые вопросы

- Используется уже принятая таблица `suppliers` и audit FK; её миграция оставлена без изменений.
- Валюта и налоговый режим задаются через `price_lists`; фактическая валюта NDI схемой не предполагается.
