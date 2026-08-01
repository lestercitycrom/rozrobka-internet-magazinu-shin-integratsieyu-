# Модель каталога и данных NDI

Дата: 2026-07-31
Статус: проектное решение для локального каталога и интеграции NDI. Это не контракт NDI и не миграции БД.

## 1. Границы и исходные факты

Магазин хранит свой поисковый каталог в локальной БД. NDI Sales API не используется для листинга или фильтрации: он принимает уже известный артикул и служит для финальной проверки availability/даты и последующего заказа. Поток данных:

```text
выгрузки NDI (SFTP/файлы)
  -> неизменяемое сырое хранилище + import_run
  -> staging/валидация
  -> нормализованный каталог, цены, складские остатки
  -> поиск и корзина в локальной БД
  -> Inquiry NDI перед оплатой -> заказ NDI после согласованного платёжного сценария
```

В исследованном пакете `tmp/ndi_api_package_20260731`:

| Источник | Роль | Наблюдение |
|---|---|---|
| `Export.xlsx` | каталог шин и базовая цена | 1 082 строк, 34 поля; ключ `ItemID`; `Gross_Price` и `Net_Price` есть у всех строк |
| `Export_1.xlsx` | каталог дисков и базовая цена | 502 строки, 14 полей; ключ `Varenummer`; `Brutto` и `Netto` есть у всех строк |
| `Export_2.xlsx` | остатки по складам | 2 304 строки; ключ `ItemID`; 7 складских колонок |
| A2 XML и документация | Inquiry и заказ | предпочтителен EDIWheel A2 по HTTPS; `ManufacturersArticleID` — рекомендуемый NDI идентификатор |
| C1 XML/документация | расширенный заказ/статус/отгрузка | требует отдельной валидации с NDI; пример C1 допускает адрес получателя и `SEAS`/`REPL` |

Все `ItemID`/`Varenummer` уникальны внутри каждой из трёх предоставленных выгрузок. Все 1 584 SKU из двух каталогов есть в файле остатков; ещё 720 строк остатков не имеют строки в этих конкретных выгрузках каталога. Это не ошибка, а состояние `stock_only`: оно не даёт права публиковать или удалять товар.

Реальный `Export_2.xlsx` также устанавливает правило остатков: во всех 2 304 строках `InStockNorway = min(сумма шести физических локаций, 40)`. В 224 строках сумма локаций больше 40, но национальное поле остаётся равным 40. Значит, `InStockNorway` — ограниченный национальный итог, а не седьмой склад и не количество, которое нужно ещё раз прибавлять к локациям.

Значения NDI-артикулов необходимо хранить как строку ASCII (`varchar`), а не число: это сохраняет ведущие нули (`063...`) и исключает потерю точности длинных идентификаторов Excel.

## 2. Источники и приоритеты

| Доменные данные | Канонический источник | Частота/свежесть | Правило использования |
|---|---|---|---|
| SKU, тип, атрибуты, изображение, базовые цены | полные файловые выгрузки NDI | ожидаемо суточно, фактический SLA не подтверждён | локальный каталог; только полный успешный набор может менять состояние «пропал из каталога» |
| Остаток по локации | `Export_2.xlsx` | ожидаемо суточно | хранить все физические локации; для витрины брать `InStockNorway`, а сумму локаций использовать только как диагностическую проверку/резервный расчёт; не смешивать с подтверждением Inquiry |
| Доступное количество и дата поставки | `inquiry_A2 -> quote_A2` | синхронно при checkout/повторной проверке | самый свежий источник решения о продаже; фактически подтверждены количество и дата |
| Цена | файловый каталог | до подтверждения NDI иного — только файл | фактический A2 quote цену не вернул; цену на checkout перепроверять по действующей политике цены, а не выдумывать из availability |
| Заказ, статус, отгрузка, трек | ответ NDI и status/despatch | после размещения заказа | отдельные журналируемые внешние сущности; не считать заказ созданным до получения NDI `OrderReference/DocumentID` |

`PartyID`, Basic credentials, адреса и XML с персональными данными не помещаются в фикстуры, логи или сырое публичное хранилище. Секреты — только в защищённом хранилище конфигурации.

## 3. Логическая модель

### 3.1 Общий каталог

| Таблица | Назначение и основные поля | Ключи/ограничения |
|---|---|---|
| `suppliers` | поставщик: `id`, `code`, `name`, `is_active` | `UNIQUE(code)`; для NDI `code='ndi'` |
| `brands` | нормализованный бренд: `id`, `canonical_name`, `slug` | `UNIQUE(canonical_name)`; исходное написание остаётся в raw-полях SKU |
| `products` | продукт витрины: `id`, `product_type` (`tire`/`wheel`), `brand_id`, `model_name`, `title`, `publication_status`, `first_seen_at`, `last_catalog_seen_at` | `CHECK(product_type ...)`; не использовать наименование как ключ |
| `supplier_skus` | продаваемая позиция: `id`, `supplier_id`, `product_id`, `external_sku`, `ean`, `supplier_item_id`, `source_description`, `catalog_state` | `UNIQUE(supplier_id, external_sku)` — главный бизнес-ключ NDI; частичный `UNIQUE(supplier_id, ean)` только при непустом EAN, если данные поставщика это допускают |
| `product_media` | изображение: `id`, `product_id`, `source_url`, `kind`, `sort_order`, `source_artifact_id` | `UNIQUE(product_id, source_url)`; URL `PlainUrl` — источник, а не доказательство наличия картинки |
| `product_source_fields` | редко используемые и ещё не типизированные поля: `supplier_sku_id`, `field_name`, `value_text`, `source_artifact_id` | `UNIQUE(supplier_sku_id, field_name, source_artifact_id)`; либо JSON-слепок строки как дополнение, но не вместо важных фильтров |

`products` разрешает со временем объединять несколько SKU/поставщиков под одной карточкой. Для первой поставки допустима связь 1:1, но внешний идентификатор всегда находится в `supplier_skus`, а не в `products`.

> Media implementation update (2026-08-01): the earlier proposed `product_media` row is superseded by `product_media_sources`, `media_assets`, and polymorphic `media_assignments`. The source table stores one current, validated NDI URL candidate per product plus source artifact, status, retry state and the downloaded asset reference. Storefronts render only locally validated active assets; manual primary assignments take precedence over supplier media.

### 3.2 Спецификации шин

| Таблица | Поля | Ключи/правила нормализации |
|---|---|---|
| `tire_specs` | `product_id`, `width_mm`, `width_inch`, `aspect_ratio`, `rim_diameter_in`, `load_index`, `speed_symbol`, `construction`, `season`, `reinforced`, `winter_type`, `three_peak_mountain_snowflake`, `ice_grip`, `run_flat`, `production_year` | `PK(product_id)`; один из `width_mm`/`width_inch` обязателен; единицы — отдельные decimal/integer поля, не строка описания |
| `tire_labels` | `product_id`, `rolling_resistance`, `wet_grip`, `noise_db`, `noise_class`, `eu_tyre_class`, `eu_label_regulation`, `eprel_url` | `PK(product_id)`; `noise_db` — integer после разбора `"71 dB"`; ссылка EPREL хранится отдельно |

Размер `245/75R16C` не является идентификатором товара: один размер имеет разные бренд, модель, индекс нагрузки, скорость, сезон, исполнение и год. `ItemDesc` хранится как исходное отображаемое описание; фильтры строятся по типизированным полям. `Speed_index` разбирается на код `Q` и при необходимости справочный лимит км/ч, но исходная строка сохраняется.

### 3.3 Спецификации дисков

| Таблица | Поля | Ключи/правила нормализации |
|---|---|---|
| `wheel_specs` | `product_id`, `width_in`, `diameter_in`, `bolt_holes`, `pcd_mm`, `offset_et_mm`, `center_bore_mm`, `bolt_hole_boring`, `color` | `PK(product_id)`; размеры/PCD/ET/DIA — decimal, число отверстий — integer |

Комбинация `width × diameter × bolt_holes × pcd × ET × DIA` — индекс для фильтра и поиска совместимости, но **не** уникальный ключ: у дисков различаются бренд, модель, цвет, посадка/сверловка и коммерческая позиция. Названия норвежских полей сопоставляются так: `Bredde -> width_in`, `Diameter -> diameter_in`, `Bolthull -> bolt_holes`, `Boltsirkel -> pcd_mm`, `Navåpning -> center_bore_mm`, `GFARVEPOL -> color`.

### 3.4 Цена, остатки и подтверждения API

| Таблица | Назначение и поля | Ключи/ограничения |
|---|---|---|
| `price_lists` | `id`, `supplier_id`, `code`, `currency`, `tax_mode` (`gross`/`net`), `valid_from`, `valid_to` | `UNIQUE(supplier_id, code, currency, tax_mode, valid_from)`; валюта выгрузки должна быть подтверждена, не выводится из чисел |
| `sku_prices` | текущее состояние: `id`, `supplier_sku_id`, `price_list_id`, `amount`, `observed_at`, `source_artifact_id` | `UNIQUE(supplier_sku_id, price_list_id)`; деньги `decimal`, никогда float |
| `sku_price_history` | история: `id`, `supplier_sku_id`, `price_list_id`, `amount`, `observed_at`, `source_artifact_id` | `UNIQUE(source_artifact_id, supplier_sku_id, price_list_id)`; отдельная таблица нужна, потому что MySQL не поддерживает partial unique index `WHERE is_current` |
| `warehouses` | `id`, `supplier_id`, `code`, `name`, `country_code` | `UNIQUE(supplier_id, code)`; исходные коды: `norway`, `vestby`, `kristiansand`, `trondheim`, `tromso`, `bergen`, `kopervik`; `Tromsø` — display-name, не ключ |
| `stock_snapshots` | `id`, `supplier_sku_id`, `warehouse_id`, `quantity`, `observed_at`, `import_run_id`, `source_artifact_id` | история: `UNIQUE(source_artifact_id, supplier_sku_id, warehouse_id)` |
| `current_stock` | материализованное текущее состояние: `id`, `supplier_sku_id`, `warehouse_id`, `quantity`, `observed_at`, `source_artifact_id` | `UNIQUE(supplier_sku_id, warehouse_id)`; surrogate `id` упрощает Eloquent, бизнес-уникальность остаётся составной; обновляется только после успешной полной загрузки запасов |
| `ndi_inquiries` | `id`, `supplier_sku_id`, `requested_qty`, `available_qty`, `delivery_at`, `error_code`, `requested_at`, `responded_at`, `request_artifact_id`, `response_artifact_id`, `checkout_id` | внешний read-model; привязка к checkout делает решение проверяемым |

Нельзя подменять `current_stock` результатом Inquiry: выгрузка описывает распределение по складам, а Quote — доступность на момент запроса и срок. Витрина использует национальный итог `InStockNorway` (в выгрузке он ограничен 40), не складывая его повторно с физическими локациями. Продажа разрешается только при свежем успешном Inquiry на требуемое количество.

`Gross_Price`/`Net_Price` шин и `Brutto`/`Netto` дисков импортируются как две раздельные записи цены с явно указанным режимом налога. Нулевая цена у диска — валидное входное значение, но не должна автоматически означать бесплатный товар: политика публикации `price=0` задаётся бизнесом (по умолчанию скрыть/запросить цену).

### 3.5 Внешние заказы и доставка

| Таблица | Назначение | Ключи |
|---|---|---|
| `ndi_orders` | связь локального заказа с NDI: `local_order_id`, `protocol`, `customer_reference`, `blanket_reference`, `ndi_order_reference`, `state`, request/response artifacts | `UNIQUE(local_order_id)`; `UNIQUE(supplier_id, ndi_order_reference)` при наличии; `UNIQUE(supplier_id, customer_reference)` как защита от повторной отправки |
| `ndi_order_lines` | line id, SKU, requested/confirmed/killed quantity, per-line error, delivery date | `UNIQUE(ndi_order_id, line_id)`; `line_id` — строковый внешний идентификатор, не автоинкремент |
| `shipments` | carrier, product, tracking number/link, global/local status, status timestamp, raw artifact | `UNIQUE(ndi_order_id, tracking_number)` при непустом треке |

До подтверждения NDI нельзя считать `PlaceOrder` безопасно повторяемым. Повтор должен сначала найти запись `ndi_orders` по локальному order/customer reference и сверить сохранённый ответ; при неизвестном исходе — запросить статус/ручную сверку, а не слепо повторять POST.

## 4. Неизменяемый сырой слой и аудит

| Таблица | Минимальные поля | Гарантия |
|---|---|---|
| `import_runs` | `id` (ULID), `supplier_id`, `run_type`, `started_at`, `finished_at`, `status`, `trigger`, `schema_version`, счётчики created/updated/skipped/rejected | жизненный цикл `running -> succeeded|failed|partial`; в production `partial` не публикует current-состояние |
| `source_artifacts` | `id`, `supplier_id`, `kind`, `original_filename`, `storage_key`, `byte_size`, `sha256`, `received_at`, `content_type`, `is_redacted` | `UNIQUE(supplier_id, sha256)`; объектное хранилище write-once, ключ по hash; сохраняется исходный Excel/XML **байт-в-байт** |
| `import_run_artifacts` | `import_run_id`, `source_artifact_id`, `dataset_role`, `parser_version` | `UNIQUE(import_run_id, source_artifact_id, dataset_role)`; один raw-артефакт можно безопасно переобработать новой версией парсера без копирования байтов |
| `import_rejections` | `id`, `import_run_id`, `source_artifact_id`, `sheet_name`, `row_number`, `external_sku`, `field_name`, `reason`, `raw_row_json` | ошибочная строка не теряется и не останавливает весь import без порога качества |
| `entity_changes` | `id`, `import_run_id`, `entity_type`, `entity_id`, `operation`, `before_json`, `after_json`, `field_diff_json`, `source_artifact_id`, `occurred_at` | объясняет, почему карточка/цена/остаток изменились |
| `external_messages` | `id`, `direction`, `operation`, `correlation_id`, `http_status`, `error_code`, `request_artifact_id`, `response_artifact_id`, `sent_at`, `received_at` | XML payload хранится с redaction секретов/PII; hash исходного сообщения сохраняется |

`sha256` служит и доказательством происхождения, и ключом дедупликации. Для Excel полезно дополнительно сохранять нормализованный hash логических строк; это позволяет отличить повторную доставку тех же данных от нового файла с другой метаинформацией. Raw-хранилище ограничивается политикой retention и доступом по ролям; оно не заменяет резервное копирование БД.

## 5. Идемпотентный импорт

Production-reader — `openspout/openspout` ветки 4.x. Он читает XLSX потоком с низким потреблением памяти и поддерживает PHP 8.3. Ветка 5.x требует PHP 8.4 и для текущего стека не используется. Импорт не должен загружать всю рабочую книгу в память; это важно при росте каталога и не зависит от размера присланных примеров.

1. Получить **полный согласованный комплект** файлов, вычислить SHA-256, создать или найти каждый `source_artifact`, создать `import_run` и связать набор через `import_run_artifacts`. Оригинальное имя файла не является идентификатором версии.
2. Если тот же набор hash уже успешно обработан той же версией парсера для тех же ролей, завершить run как `duplicate` без обновления доменных таблиц. Запись о попытке и связь с источником остаются; новая версия парсера имеет право переобработать существующий raw-артефакт.
3. Распарсить в staging с `row_number`, исходной строкой и строковыми внешними идентификаторами. Проверить: обязательный SKU, уникальность SKU внутри файла, числовые поля/неотрицательные остатки/цены, допустимый тип товара, неизвестные колонки и ожидаемую полноту набора. Для stock-файла проверить `InStockNorway = min(сумма физических локаций, configured_cap)`, где подтверждённый для текущей выгрузки cap равен 40.
4. Применить upsert только по естественному ключу `(supplier_id, external_sku)` в одной транзакции. Создание/изменение карточки, атрибутов, цен и snapshot-остатков фиксируется в `entity_changes`.
5. Обновить `current_stock` replace-set операцией по полному успешному `Export_2`: отсутствующие в **этом полном stock-файле** известные SKU/склады становятся нулём с явным provenance. Не выполнять это правило при частичном/повреждённом файле.
6. Опубликовать новое current-состояние только после всех проверок. При ошибке сохранить rejection/audit, оставить последнюю успешную витрину неизменной и поднять alert.

Импорт должен быть устойчив к повторному запуску после сбоя: повтор с тем же artifact либо не меняет current-слой, либо даёт тот же итог. Автоинкрементные `id`, время запуска и порядок строк не используются для business-deduplication.

### Пропавшие и несогласованные позиции

* Не удалять SKU каскадно: они могут быть в корзинах, заказах и истории.
* SKU из stock-файла без строки текущего каталога получает `catalog_state='stock_only'`, `publication_status='draft'`, очередь на выяснение. В предоставленных файлах таких строк 720.
* SKU, пропавший из авторитетного полного каталог-файла, пометить `missing_from_catalog_at`; цену можно прекратить публиковать сразу, но товар скрывать только после как минимум двух последовательных валидных полных каталог-импортов либо подтверждённого срока устаревания. Число и срок — конфигурация, не захардкоженное поведение.
* Если SKU остаётся в остатках, но пропал из каталога, он не отключается автоматически: статус `needs_enrichment` и ручная проверка. Если отсутствует в обоих источниках после grace-периода — `inactive`, а не физическое удаление.
* Новая товарная строка без остатка создаётся как каталоговая, но не продаётся, пока policy availability не допускает её продажу и/или пока Inquiry не подтвердит наличие.

## 6. Контроль качества, фикстуры и тесты

Фикстуры должны быть versioned, без секретов и PII; базовый набор строится из предоставленного пакета только после редактирования примеров:

| Фикстура/сценарий | Что проверяет |
|---|---|
| `tires_valid.xlsx` и `wheels_valid.xlsx` (малые redacted подмножества) | все типизированные поля, ведущий ноль в SKU, цены net/gross, неполные label-поля |
| `stock_all_warehouses.xlsx` | национальный итог + 6 физических локаций, нулевой и положительный остаток, cap=40 без потери локаций и двойного суммирования |
| `stock_only_sku.xlsx` | SKU в остатках без карточки: создаётся только состояние `stock_only`, не опубликованный товар |
| `duplicate_sku.xlsx`, `invalid_decimal.xlsx`, `negative_stock.xlsx` | rejection с точными строкой/полем, current-слой не портится |
| точная копия ранее принятого artifact | повторный import не создаёт дубликатов и сохраняет исходный итог |
| полный файл с пропавшим SKU / частичный файл | grace-policy применяется только к полному набору; частичный файл ничего не деактивирует |
| `inquiry_success.xml`, `inquiry_error.xml` | namespace A2, `ErrorCode`, available quantity, дата поставки и redaction audit trail |
| `order_response_success.xml`, status/despatch XML | сохранение NDI `OrderReference`, line-idempotency и tracking/status history |

Отдельные интеграционные тесты проверяют транзакционный rollback, блокировку параллельных import для одного supplier+dataset и восстановление после сбоя между staging и publish. Контрактные XML-фикстуры валидируются по сохранённой версии схемы; URL к внешней документации не является тестовой зависимостью.

## 7. Риски и открытые решения

| Риск/неизвестность | Влияние | Решение до запуска |
|---|---|---|
| Правильный `PartyID` для выданных credentials не подтверждён | Inquiry/order от имени EasyTire невозможен или неверен | получить письменное подтверждение NDI и прогнать read-only Inquiry |
| Quote фактически не возвращает цену | можно принять оплату по устаревшей цене | зафиксировать price freshness/SLA и сценарий расхождения цены; не считать Quote источником цены без подтверждения |
| Нет подтверждённого reserve/cancel | риск оплаты без товара и повторного заказа | согласовать authorization/capture или ручной recovery flow; запретить blind retry `PlaceOrder` |
| Выгрузки вероятно суточные, SLA/rate limit неизвестны | устаревший каталог и нестабильные checkout | мониторить age последнего successful import, rate-limit/retry с backoff, получить SLA NDI |
| 720 stock-only SKU | потеря ассортимента или случайная публикация неполных карточек | оставлять в quarantine до полного каталога/ручного enrichment |
| Локализация полей и Excel-формат | неверный decimal/кодировка/сопоставление колонок | schema version, явные mapping, decimal parser, contract tests на реальных redacted примерах |
| C1/Soap материалы неоднородны; SOAP отмечен устаревшим | неверный протокол или небезопасный HTTP | production-интеграция по HTTPS EDIWheel A2; C1 включать лишь после подтверждения NDI и теста |
| Данные заказа/трек содержат PII | утечка через logs/raw | шифрование at rest, RBAC, redaction и retention policy |

## 8. Результирующие решения

1. Единый ключ позиции NDI — `(supplier='ndi', external_sku)` из `ItemID`/`Varenummer`, строковый и неизменяемый; EAN и описание — только альтернативные атрибуты.
2. Каталог, цена и остаток — разные нормализованные слои с собственной историей и provenance; ни размер, ни PCD, ни название не используются как SKU-ключ.
3. Каждая выгрузка и XML-сообщение сохраняется неизменяемо с SHA-256, import runs и полным аудитом изменений.
4. Только полный успешный import меняет published current-state и может начать жизненный цикл «пропавшей» позиции; физического удаления нет.
5. Перед оплатой обязателен A2 Inquiry по выбранному SKU/количеству. Размещение заказа идемпотентно управляется локальной записью/референсом и ответом NDI, а не повторной отправкой запроса.
