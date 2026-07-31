# EasyTire application

Laravel-приложение магазина и сервисных услуг EasyTire. Витрина, собственная административная панель, каталог, checkout и внешние интеграции находятся в одной кодовой базе.

Архитектурные решения и требования находятся в корневом каталоге `../docs/`.

## Требования

- PHP 8.3+;
- Composer 2;
- Node.js 22+ для локальной разработки и CI;
- SQLite для быстрого локального старта;
- MySQL 8 для staging/production и интеграционных тестов каталога.

## Локальный старт

```text
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

На Windows в текущем окружении PHP находится в `C:\OSPanel\modules\PHP-8.3`; этот каталог нужно временно добавить в `PATH` текущего терминала. Локальный `.env` и любые внешние credentials запрещено коммитить.

## Проверки

```text
composer test
npm run build
npm run test:e2e
```

Pest покрывает unit/feature/integration/security/architecture, Playwright — сценарии покупателя и администратора. Браузерные тесты не имеют права создавать реальные NDI-заказы, платежи или внешние уведомления.

## Staging

Ветка `main` автоматически разворачивается после push в Git remote `staging`. Серверный hook собирает отдельный release, запускает миграции и тесты и только после успеха атомарно переключает активную версию.
