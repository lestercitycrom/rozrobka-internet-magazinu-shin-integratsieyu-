# NDI media coverage — status and next delivery

**Date:** 12 August 2026
**Scope:** live EasyTire catalogue imported from NDI Norge AS

## Verified coverage

| Product type | Products | Products with an official image | Without image | Coverage |
|---|---:|---:|---:|---:|
| Tyres | 1,082 | 967 | 115 | 89.4% |
| Wheels | 502 | 0 | 502 | 0.0% |
| **Total** | **1,584** | **967** | **617** | **61.0%** |

Of the 115 tyre products without an image, 87 currently have positive stock. All 502 wheel products lack an image; 372 of them currently have positive stock. In total, 459 in-stock products need supplier media.

The 967 tyre images consist of 621 explicit official NDI sources and 346 safe exact inheritances. This reconciles exactly: 621 + 346 = 967.

## Missing tyre image groups

The unpictured tyre products belong to nine exact brand + pattern groups. The count in parentheses is the number with positive stock.

| Brand | Exact pattern | Missing products | In stock |
|---|---|---:|---:|
| Nexen | Winguard ice 3 | 79 | 72 |
| Nexen | Winguard Winspike WS62 | 14 | 5 |
| Nexen | N'blue S | 10 | 6 |
| Nexen | Winguard Winspike SUV | 4 | 0 |
| Nexen | N'Blue S EV | 2 | 2 |
| Nexen | Roadian 541 | 2 | 1 |
| Nexen | Roadian HP | 2 | 0 |
| Nexen | CP321 | 1 | 0 |
| Nexen | Roadian GTX EV | 1 | 1 |
| **Total** |  | **115** | **87** |

## What is already safe and implemented

NDI confirmed that it may pass marketing rights for the images to EasyTire and allows reuse only for an **exact** match:

- tyre: same manufacturer and exact tread pattern;
- wheel: same manufacturer and exact model.

The importer applies this rule only when the product type and normalized exact name match and the group has a single explicit official NDI URL. Conflicting or fuzzy matches are not inherited. Administrator-selected images remain primary and all inherited images retain NDI provenance.

This safe reconciliation has already been exhausted for the currently received files. There are no pending or failed downloads among the known official sources.

## What must not be done

We must not fill the gaps by scraping Google, manufacturer websites, NDI’s shop, image search, or by guessing from tyre dimensions/appearance. Such images could depict another version, lack a clear usage right, or silently associate the wrong product with a listing.

The storefront therefore keeps an intentional, neutral “image not supplied” presentation for products where NDI has not supplied an official image. Hiding those products would remove 502 wheel listings and 87 in-stock tyre listings from customers.

## Exact next input needed from NDI

1. The actual updated **wheel workbook** containing a populated `PlainURL` / `PlainUrl` column for the 502 wheel articles. NDI stated that this column had been added, but the updated workbook itself was not included in the received material.
2. Official `PlainURL` values (or another NDI-approved official image reference resolvable to a URL) for the nine tyre patterns in the table above that still have no donor image.

After receipt, the existing importer can fetch, validate and assign the images automatically; no manual matching or catalogue rewrite is needed.

## Ready-to-send request to NDI (Norwegian)

> Hei,
>
> Takk for avklaringen om produktbilder. Vi har nå importert og kontrollert materialet. Dekkbildene kan brukes der det finnes en eksakt match på produsent og mønster, og dette er allerede håndtert i importen.
>
> For å få resten på plass trenger vi to ting:
>
> 1. Den oppdaterte felgfilen dere nevnte, med utfylt kolonne `PlainURL`/`PlainUrl` for felgartiklene. Den var ikke med i materialet vi mottok.
> 2. Offisielle `PlainURL`-er (eventuelt en annen offisiell NDI-bildereferanse som kan løses til URL) for disse dekkmønstrene som fortsatt mangler bilde: Nexen Winguard ice 3, Winguard Winspike WS62, N'blue S, Winguard Winspike SUV, N'Blue S EV, Roadian 541, Roadian HP, CP321 og Roadian GTX EV.
>
> Når vi mottar dette, importeres bildene automatisk. På forhånd takk!

## Русский перевод

> Добрый день!
>
> Спасибо за пояснения по изображениям товаров. Мы импортировали и проверили полученные материалы. Изображения шин уже используются там, где есть точное совпадение производителя и рисунка протектора; это уже реализовано в импорте.
>
> Чтобы завершить работу с остальными товарами, нам нужны две вещи:
>
> 1. Обновлённый файл по дискам, о котором вы писали, с заполненной колонкой `PlainURL`/`PlainUrl` для артикулов дисков. В полученных нами материалах этого файла не было.
> 2. Официальные `PlainURL` (или другая официальная ссылка NDI на изображение, которую можно получить как URL) для следующих рисунков шин, у которых пока нет изображения: Nexen Winguard ice 3, Winguard Winspike WS62, N'blue S, Winguard Winspike SUV, N'Blue S EV, Roadian 541, Roadian HP, CP321 и Roadian GTX EV.
>
> Как только мы получим эти данные, изображения будут импортированы автоматически. Спасибо заранее!
