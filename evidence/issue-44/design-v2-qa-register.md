# EasyTire design v2 — QA register

Дата сверки: 15.09.2026. Основа: `qa-register-v2.json`; кандидат в рабочем дереве ветки `codex/issue-44-design-v2`, принятая база `bf7f03e3d3265174e4e4307064769a742dd087cc`. Это честный внутренний read-back, а не разрешение production.

Статусы: **PASS** — конкретный контракт подтверждён имеющимся тестом/измерением; **PARTIAL** — проверенная часть и явно указанная непроверенная граница; **PENDING** — запущенная итоговая проверка ещё не завершена; **BLOCKED** — нужен внешний источник, физическое устройство или решение владельца.

Активный application/design-SHA: **980fdc117d27a7bcf5d0d0cd950e31e74cee1497**, [финальный CI 34961146057](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/34961146057). Build, Pint, validator и все три CI job PASS. Интегрированный admin browser-прогон: 11/11 на desktop/390/320 без retries; video stress: 100/100 и отдельный runtime-case 50/50 без retries. Live smoke точного SHA: 7/7 для главной, каталога, услуг и admin-login на 1440/390/320, без page overflow, browser errors и local-origin asset leaks. Активные ассеты: CSS `app-DNYdr7i2.css`, JS `app-CKCevDiM.js`.

Итог CI точного SHA: **все 3 job SUCCESS; 544 PHP PASS / 5213 assertions; Playwright 251 PASS / 132 ожидаемых SKIP / 0 retries / 0 FAIL**, 383 запланированных теста, 10,2 мин. Прежняя нестабильность video lazy-load/manual-pause/reduced-motion устранена детерминированным разделением сценариев без ослабления assertions. Итоговая таблица: 42 PASS, 17 PARTIAL, 5 BLOCKED; PENDING нет. Частичные/внешние границы не замаскированы общим зелёным CI.

История: версия `7363622`, [CI 34927746672](https://github.com/lestercitycrom/rozrobka-internet-magazinu-shin-integratsieyu-/actions/runs/34927746672): все 3 job SUCCESS, 543 PHP PASS / 5201 assertions, Playwright 247 PASS / 1 FLAKY retry-pass / 124 ожидаемых SKIP. Единственный retry касается reduced-motion. Ранние failures/повторы сохранены в `design-v2-result.md`; они не суммируются как новый полный прогон. Family-capture: 40 локальных, 18 live на `7363622`, home-повторы на последующих версиях. Измерение CLS 0.0001404 / LCP 948 ms на `7363622` — локальное, не полевое и не новый замер финального SHA.

| ID | Статус | Фактическая проверка и честная граница |
|---|---|---|
| QA-01 | PASS | База, ветка и источники зафиксированы. Staging marker/REVISION = 980fdc117d27a7bcf5d0d0cd950e31e74cee1497; непосредственный rollback 33694cdb5a41012d6bbb95e548a4eb7d30f0ee3b, принятая база, .env и shared storage сохранены. Финальная staging-передача разрешена PM 5678136657; production не разрешён. |
| QA-02 | PASS | Общие роли/токены проверяют `StorefrontInteractionStylesTest.php`, `StorefrontVisualStyleTest.php`, `design-foundation.spec.ts`; случайный wildcard repair-cascade запрещён тестом ownership. |
| QA-03 | PASS | Контейнеры, DS-1 интервалы и стыки секций проверены в `design-foundation.spec.ts`, `homepage-composition.spec.ts` и family-captures без page overflow. |
| QA-04 | PASS | «Dekk og felger», active-state `/priser`, порядок и общие контакты покрывают `CustomerNavigationRevisionTest.php`, `StorefrontNavigationTest.php`, `header-navigation.spec.ts`. |
| QA-05 | PASS | Mega/mobile menu, поиск и реальная клавиатурная передача focus проверены `interaction-shell.spec.ts`, `header-navigation.spec.ts`; normal keyboard menus/dialogs прошли. |
| QA-06 | PASS | Hero/finder на desktop/mobile и text-200% proxy проверены `design-foundation.spec.ts`, `home-hero-process.spec.ts`. Финальный focused 5/5 + dual-tab 1/1: обе компактные подписи однострочные, полные accessible-name и реальные переключения сохранены; 320/390 и 200% без page overflow/внутреннего обрезания категорий. |
| QA-07 | PASS | Порядок hotel→prices→promos→additional→process→main и отсутствие нижнего finder подтверждены `homepage-composition.spec.ts`, `CustomerHomepageCompositionSeederTest.php`. |
| QA-08 | PARTIAL | Реальный lazy video fallback, poster, mute/pause и CMS override покрыты `issue-44-release-qa.spec.ts`, `HomepageContentManagementTest.php`. Монтаж 28 s и media rights не имеют отдельного внешнего согласования. |
| QA-09 | PASS | Native dialog: точный контент, Escape, reverse Tab, focus return, blocked background и mobile inner-scroll проверены `issue-44-release-qa.spec.ts`. |
| QA-10 | PASS | Геометрия, 0–4 записей, long copy и breakpoint controls подтверждены `homepage-content-edge-cases.spec.ts`, `homepage-feedback.spec.ts` и завершённой CI. |
| QA-11 | PASS | Стабильная CTA-геометрия, keyboard/touch dialog, delayed hover preview и reduced-motion покрыты `homepage-content-edge-cases.spec.ts`, `interaction-shell.spec.ts`, `StorefrontInteractionStylesTest.php`. |
| QA-12 | PASS | Ручная карусель, controls/count и отсутствие focus у скрытых карточек проверяют `homepage-feedback.spec.ts`, `homepage-content-edge-cases.spec.ts`; autoplay не используется. |
| QA-13 | PARTIAL | Aspect/fallback/назначения проверены `ServiceMediaCatalogTest.php`, `MediaHeroRenderingTest.php` и captures. Финальный manifest уникальности сюжетов и прав всех CMS-фото не предоставлен. |
| QA-14 | PASS | Четыре шага, порядок и responsive 4/2/1 проверены `home-hero-process.spec.ts`, `CustomerHomepageCompositionSeederTest.php`. Финальный geometry-test и live 33694cd: иконка явно в col1/row1 без лишнего track, текст 152 px на 320/200%, номер под иконкой. |
| QA-15 | PASS | Независимые 2 feature + 4 compact группы и CTA покрыты `HomepageServiceCollectionsTest.php`, `service-composition.spec.ts`, `homepage-composition.spec.ts`. |
| QA-16 | BLOCKED | Карточки и полный текст условий сохранены (`CustomerHomepageCollectionsSeederTest.php`), но год, точные даты и stacking rules не подписаны. Новые скидочные расчёты намеренно не придуманы. |
| QA-17 | BLOCKED | Адрес Ringvegen 200 и въезд через 202 покрыты `CustomerNavigationRevisionTest.php`; официальный Snapchat URL в первичных экспортных материалах отсутствует. |
| QA-18 | PASS | Четыре партнёра, footer/logo, legal links и отсутствие выдуманных отзывов проверены `HomepageTrustManagementTest.php`, `interaction-shell.spec.ts`, `project-content.spec.ts`. |
| QA-19 | PASS | Discoverable finder/store entry без второго пустого hero проверяют `StorefrontNavigationTest.php`, `design-foundation.spec.ts`, `catalog-mobile-layout.spec.ts`. |
| QA-20 | PASS | Complete-wheel routes/prefill сохраняют правильную услугу; покрытие: `VehicleFitmentRequestPrefillTest.php`, `CustomerNavigationRevisionTest.php`, `service-requests.spec.ts`. |
| QA-21 | PARTIAL | View различает unfiltered-empty и no-match, а network error имеет retry (`PublicCatalogTest.php`, `catalog-live.spec.ts`, `catalog-mobile-layout.spec.ts`); backend freshness gate сохранён. Live read-only 15.09: 1585 товаров, 1574 опубликованы, 16129 stock-записей; последний observed stock `2026-09-04 08:00:06`, порог 2160 минут; 918 SKU до gate, 0 после. Данные/API/config не менялись; product/cart проверены синтетически. Отдельного UI-контракта feed outage/stale supplier нет. |
| QA-22 | PASS | URL/UI/history, last-request-wins и rail overflow проверяют `catalog-live.spec.ts`, `catalog-mobile-layout.spec.ts`, `PublicCatalogTest.php`. |
| QA-23 | PARTIAL | Registration errors, retained input и manual fallback покрыты `VehicleFitmentTest.php`, `VehicleFitmentRequestPrefillTest.php`; реальные внешние fitment-ответы не вызывались. |
| QA-24 | PASS | Цена/размер/stock/quantity и honest media placeholder проверены `PublicCatalogTest.php`, `product-detail.spec.ts`, `cart-composition.spec.ts`, `StorefrontMoneyTest.php`. |
| QA-25 | PARTIAL | Арифметика, unavailable/price change, idempotency и zero external work покрыты `CommerceCoreTest.php`, `CartSecurityTest.php`, `checkout.smoke.spec.ts`. Реального списания/provider E2E не было. |
| QA-26 | PASS | Public `kr`, 0/2 decimals и сохранение machine `NOK` проверены `StorefrontMoneyTest.php`, `PublicServicePriceManagementTest.php`, `SeoLandingPagesTest.php`; суммы исходного прайса не менялись. |
| QA-27 | PARTIAL | Price CTA ведут к точным service/fulfilment intents без обещания слота (`PublicServicePriceManagementTest.php`, `SeoLandingPagesTest.php`, `service-requests.spec.ts`). Rim/price-range не переносится автоматически: размер остаётся явным выбором формы. |
| QA-28 | PASS | Первый шаг/поле, named progress и back-retention проверены `design-foundation.spec.ts`, `service-requests.spec.ts` на desktop/390/320. |
| QA-29 | PASS | Service-specific required/optional поля покрыты `ServiceRequestTest.php`, `BatteryServiceRequestContractTest.php`, `PublicValidationLocalizationTest.php`, `service-requests.spec.ts`. |
| QA-30 | PASS | Mobile/workshop взаимно исключают address и сохраняют variant/price; покрытие `SeasonalServiceQuoteTest.php`, `ServiceRequestTest.php`, `service-requests.spec.ts`. |
| QA-31 | PASS | Storage-only, отдельные hotel/смена, обязательный reg number и честная цена проверены `SeasonalServiceQuoteTest.php`, `service-requests.spec.ts`. |
| QA-32 | PARTIAL | Pickup сохраняется отдельным intent и не превращается в paid mounting (`service-requests.spec.ts`). Бесплатность/даты кампании остаются неподписанным business source. |
| QA-33 | PASS | Conditional already-stored, storage period и скрытая валидация покрыты `SeasonalServiceQuoteTest.php`, `service-requests.spec.ts`. |
| QA-34 | PASS | Независимые 1/2/5 авто, сокращение массива, путь 3+ и `LAST` проверены `ServiceRequestVehiclePayloadIssue65Test.php`, `SeasonalServiceQuoteTest.php`, `service-requests.spec.ts`. |
| QA-35 | PASS | Unknown size остаётся диапазоном, known size даёт согласованный quote, stale result сбрасывается; покрытие `SeasonalServiceQuoteTest.php`, `service-requests.spec.ts`. |
| QA-36 | PASS | Exact slot/unconfirmed period, Oslo ordering/capacity, conflict, активная admin-навигация и delete-confirm проверены `BookingCalendarTest.php`, `BookingCalendarManagementTest.php`, `service-requests.spec.ts`. Live-конфигурация содержит 0 intervals и 0 blackouts, поэтому слоты не обещаются. |
| QA-37 | PASS | Back и server-validation сохраняют поля и возвращают focus к ошибке; проверено `service-requests.spec.ts`, `PublicValidationLocalizationTest.php`. |
| QA-38 | PASS | Submission token/idempotency и sending/success/error границы покрыты `BookingCalendarTest.php`, `BatteryServiceRequestContractTest.php`, `ServiceRequestIntakeTest.php`. |
| QA-39 | PASS | Synthetic public request получает ID и виден в admin с service/source/fields; покрытие `ServiceRequestTest.php`, `ServiceRequestCrmFieldsTest.php`, `admin.service-requests.spec.ts`. Внешние уведомления отключены. |
| QA-40 | PASS | Общий service-template, SEO body, 13 опубликованных destinations и CTA service покрыты `ServicePagesTest.php`, `ServiceMediaCatalogTest.php`, `SeoLandingPagesTest.php`, `service-composition.spec.ts`. |
| QA-41 | PARTIAL | CTA-семантика/пользовательский текст покрыты `CustomerCopyContractTest.php`, `SeoLandingPagesTest.php`. Два клиентских audio не расшифрованы, поэтому полная редакционная приёмка текста заблокирована источником. |
| QA-42 | PARTIAL | PDF/404/privacy/consent технически покрыты `ContentPageManagementTest.php`, `TechnicalSeoTest.php`, `PublicValidationLocalizationTest.php`; юридическая достаточность требует владельца/специалиста. |
| QA-43 | PARTIAL | 40 local family-captures и 18/18 live page×viewport на SHA 7363622: ожидаемые статусы, page overflow=0, нет битых изображений/page errors. Дополнительный home 3/3 с обычной lazy-load прокруткой и настоящими кликами. Google Maps заблокирован, проверен fallback. Физические iOS/Android не проверены. |
| QA-44 | PARTIAL | Normal keyboard menus/dialogs и text-200% proxy PASS (`interaction-shell.spec.ts`, `issue-44-release-qa.spec.ts`, `design-foundation.spec.ts`). Это не полная WCAG/ручная contrast-сертификация. |
| QA-45 | PARTIAL | Замер реального HTML версии 7363622 с preload, без test injection: CLS 0.0001404, LCP 948 ms на 1440 px Chromium unthrottled; исходный CLS 0.109938. Это не новый замер финального SHA; реальный INP/mobile field data отсутствуют. |
| QA-46 | PARTIAL | Natural full-save, independent collections и media assignment покрыты `HomepageContentManagementTest.php`, `HomepageServiceCollectionsTest.php`, `admin-homepage-content.spec.ts`. Snapchat/calendar/media-manifest открыты. |
| QA-47 | PASS | CI 34961146057 точного 980fdc1 завершён SUCCESS во всех трёх job: 544 PHP PASS / 5213 assertions; 251 browser PASS, 132 ожидаемых SKIP, 0 retries и 0 FAIL. Video-сценарий прошёл first-pass; reachable-history secret scan и MySQL schema regression также успешны. |
| QA-48 | BLOCKED | P0 submit/checkout имеют synthetic coverage; staging preview выложен и версия подтверждена. Независимая проверка владельцем/PM/ChatGPT Pro, письменная приёмка отклонений и разрешение production не получены. |
| QA-49 | PARTIAL | Capture metadata использует CSS viewport и отдельно фиксирует dimensions; пустоты не классифицированы по PNG. Это Chromium presets, не паспорта физических устройств. |
| QA-50 | PASS | First useful action/field на 390/320, home/catalog/pricing/empty cart проверены `design-foundation.spec.ts`, `catalog-mobile-layout.spec.ts`, `cart-mobile.spec.ts` и final captures. |
| QA-51 | PASS | Короткое mobile menu/search: keyboard focus handoff/order и возврат проверены `interaction-shell.spec.ts`, `header-navigation.spec.ts`; фон modal недоступен. |
| QA-52 | PASS | Touch finder, буквенный reg.nr, retained errors/manual path и tab semantics проверены `design-foundation.spec.ts`, `VehicleFitmentTest.php`, `catalog-mobile-layout.spec.ts`. |
| QA-53 | PASS | Mobile drawer/category rail, длинные filters, buttons и разные empty states покрыты `catalog-mobile-layout.spec.ts`, `catalog-live.spec.ts`, `PublicCatalogTest.php`. |
| QA-54 | PASS | 2/3/4-card mobile reachability, stable CTA и context подтверждены `homepage-content-edge-cases.spec.ts`, `homepage-feedback.spec.ts`, финальным no-JS 390/320 и завершённой CI. |
| QA-55 | PARTIAL | Input types/autocomplete, reg.nr и server validation покрыты `service-requests.spec.ts`, `PublicValidationLocalizationTest.php`. Реальная экранная клавиатура/autofill на устройстве не проверены. |
| QA-56 | PASS | Dialog close/title/content, reverse Tab, background lock, one inner scroll и restored opener проверены `issue-44-release-qa.spec.ts` на узких/низких viewport. |
| QA-57 | PASS | Lazy video/poster, initial reduced-motion/manual playback, одно доставленное runtime-изменение, modal/offscreen pause, media error fallback и reserved layout проверены. Ненаблюдаемый быстрый media-query round-trip удалён из теста, product JS не менялся. Чистый stress-run: 100/100 PASS за 3,3 мин, 0 retries; отдельный runtime-case 50/50 PASS. |
| QA-58 | PARTIAL | Form back/panel retention покрыт `service-requests.spec.ts`; физический rotate и все состояния при live breakpoint switch отдельно не проверялись. |
| QA-59 | BLOCKED | Chromium/WebKit emulation не считается real Safari/Chrome. Результатов на физических iOS Safari и Android Chrome нет. |
| QA-60 | PARTIAL | Address wrapping, approved HTTPS map/fallback, footer links и touch-size layout покрыты `StorefrontMapTest.php`, `interaction-shell.spec.ts`, captures. Официальный Snapchat отсутствует. |
| QA-61 | PASS | Service photo/text/CTA natural height на 320 и общие роли проверены `service-composition.spec.ts`, `ServiceMediaCatalogTest.php`, family-captures. |
| QA-62 | PASS | Network/error retry, stale-response suppression, retained input и idempotent authoritative success покрыты `catalog-live.spec.ts`, `service-requests.spec.ts`, `BookingCalendarTest.php`. |
| QA-63 | PASS | Общая модель service/source/intent и отсутствие дублей покрыты PHP/admin suites. Diff `980fdc1` от принятой базы пуст в `application/app`, `application/config`, `application/database`, `application/routes` и dependency manifests; presentation/admin-изменения находятся вне этих защищённых областей. Интегрированный admin desktop/390/320: 11/11 PASS без retries; data/API/настройки не менялись. |
| QA-64 | BLOCKED | Полные family-captures и этот 64-row register готовы как внутренняя база; автоматического baseline update нет. Независимая проверка целых staging-страниц владельцем/PM/ChatGPT Pro остаётся pending. |

## Открытые решения перед финальным запуском

- Владелец: год/даты/stacking кампаний, официальный Snapchat, права/уникальность media и содержание двух audio.
- QA: physical iOS/Android при наличии устройств. Прежний video-flaky не скрыт: его причина доказана и тест исправлен по наблюдаемым browser-events; 100/100 локальных повторов прошли без retry.
- Release: staging version/rollback уже зафиксированы; независимую проверку владельца/PM/ChatGPT Pro и отдельное разрешение production не подменять этой таблицей.
