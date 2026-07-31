# WHITE-030: Norsk offentlig vitrine

Status: done

## Result

The default Laravel welcome screen at `/` is replaced with a responsive, accessible Bokmål foundation for EasyTire. It includes a future-facing search/catalog section and the confirmed service categories from the site inventory, while intentionally omitting prices, products, campaigns, contact details, and unverified promises.

## Verification

- `php artisan test tests/Feature/ExampleTest.php` — passed.
- `npm run build` — passed.
- `playwright test tests/E2E/buyer.smoke.spec.ts` — passed for buyer desktop and buyer mobile.
