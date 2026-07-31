# GREEN-010: собственная административная оболочка

Исполнитель: Mister Green  
Модель: Codex Terra high  
Статус: done

## Цель

Реализовать безопасную административную авторизацию, локали `nb`/`ru` и первый экран собственной чёрно-белой админки.

## Запреты

Filament, Nova, Backpack, Orchid, Flux UI, starter kits, dashboard templates и хранение business logic в представлениях.

## Критерии приёмки

Гость, клиент и неактивный пользователь не получают доступ; login защищён throttling/session regeneration/CSRF; locale сохраняется только для администратора; Playwright выполняет login, переключение языка и logout; buyer-тесты не ломаются.

## Приёмка Mister Red

Принято после отдельного security review. Привилегированные поля пользователя закрыты от массового присваивания, проверка закреплена тестом. Pest: 19 тестов / 64 assertions. Playwright: buyer desktop, buyer mobile и admin desktop — 3/3. Production build и Pint прошли. Интерфейс проверен визуально в браузере.
