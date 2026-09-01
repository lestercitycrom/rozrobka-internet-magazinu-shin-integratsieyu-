# Issue #28 validation-localization evidence

All screenshots were generated locally with the hermetic Playwright storefront seeder. They contain only empty fields and deliberately invalid synthetic values (`invalid`, `123`); no customer or production data is present.

## Accepted before state

- `evidence/issue-26/29-validation-bokmaal-1440-full.jpg`
- `evidence/issue-26/30-validation-russian-1440-full.jpg`

Those accepted audit captures show raw framework keys such as `validation.required` and `validation.accepted`.

## After state in this directory

- `service-validation-nb-1440.png` — generic request, Bokmål desktop.
- `service-validation-nb-390.png` — generic request, Bokmål mobile.
- `service-validation-ru-1440.png` — generic request, hidden Russian QA route.
- `checkout-validation-nb-1440.png` — checkout, Bokmål desktop.
- `checkout-validation-nb-390.png` — checkout, Bokmål mobile.
- `checkout-validation-ru-1440.png` — checkout, hidden Russian QA route.

The corresponding Playwright assertions verify natural localized messages, `aria-invalid`, error association, focus on the first invalid field and the absence of unresolved technical translation keys.
