# RED-010: ядро импорта NDI

Исполнитель: Mister Red  
Статус: done

## Цель

Реализовать поток `raw artifact -> schema detection -> typed rows -> validation -> transactional publish` для фактических XLSX NDI.

## Архитектурные решения Mister Red

- потоковое чтение через OpenSpout 4.x на PHP 8.3;
- тип файла определяется заголовком, а не именем;
- SKU остаётся строкой и не нормализуется численно;
- raw-файл сохраняется до парсинга по SHA-256;
- повтор того же набора и parser version не меняет current state;
- неизвестная/неполная схема и порог ошибок запрещают публикацию;
- stock-only остаётся в quarantine;
- реальные NDI-сеть и credentials в тесте не используются.

## Первый критерий готовности

Шесть синтетических XLSX дают ожидаемые accepted/rejected результаты; повторный валидный импорт идемпотентен; сбой/плохой файл оставляет предыдущий published state неизменным; отчёт доступен будущей собственной админке.

## Результат

- OpenSpout 4.x читает XLSX потоково, нормализованные строки складываются во временный NDJSON-поток без загрузки книги целиком в память.
- Raw-файл записывается до разбора, дедуплицируется по SHA-256 и повторно проверяется по размеру и хешу.
- Полный bundle `tires + wheels + stock` публикуется в одной транзакции; неполный/плохой bundle и искусственный сбой publisher не меняют current state.
- Реализованы audit runs, artifacts, rejections, entity changes, price/stock history, блокировка параллельного запуска и команда `ndi:import`.
- Валидная фикстура создаёт 6 товаров, 7 supplier SKU (один `stock_only`), 12 текущих цен и 49 текущих складских наблюдений. Повтор того же bundle/parser version имеет статус `duplicate`.
- Полный Pest: 42 теста / 705 assertions. Playwright: buyer desktop, buyer mobile, admin desktop — 3/3. Build и Pint прошли.
