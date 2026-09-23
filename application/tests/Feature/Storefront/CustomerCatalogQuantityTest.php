<?php

use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogItem;
use App\Modules\Catalog\ReadModel\CatalogStock;
use App\Modules\Pricing\Data\SellingPrice;
use App\Modules\Pricing\ValueObjects\Money;

test('catalog cards expose independently labelled stock-capped quantity controls', function (): void {
    app()->setLocale('nb');

    $first = customerQuantityCard('CATALOG-QTY-FIRST', 6);
    $second = customerQuantityCard('CATALOG-QTY-SECOND', 75);
    $html = view('storefront.catalog._card', $first)->render()
        .view('storefront.catalog._card', $second)->render();

    $document = customerQuantityDocument($html);
    $xpath = new DOMXPath($document);
    $inputs = $xpath->query('//input[@data-testid="catalog-product-quantity"]');

    expect($inputs)->toHaveCount(2);

    $ids = [];
    foreach ($inputs as $index => $input) {
        $id = $input->getAttribute('id');
        $ids[] = $id;

        expect($id)->toStartWith('catalog-quantity-')
            ->and($input->getAttribute('type'))->toBe('number')
            ->and($input->getAttribute('name'))->toBe('quantity')
            ->and($input->getAttribute('value'))->toBe('1')
            ->and($input->getAttribute('min'))->toBe('1')
            ->and($input->getAttribute('max'))->toBe($index === 0 ? '6' : '40')
            ->and($input->hasAttribute('readonly'))->toBeFalse()
            ->and($xpath->query('//label[@for="'.$id.'"]'))->toHaveCount(1);
    }

    expect(array_unique($ids))->toHaveCount(2)
        ->and($xpath->query('//input[@type="hidden" and @name="quantity"]'))->toHaveCount(0)
        ->and($xpath->query('//form[contains(@class, "et-product-card__purchase")]/input[@type="hidden" and @name="sku"]'))->toHaveCount(2)
        ->and($xpath->query('//form[contains(@class, "et-product-card__purchase")]//button[@data-testid="catalog-add-to-cart"]'))->toHaveCount(2)
        ->and($html)->toContain('Antall: Nordlys Arctic');
});

test('integration test catalog quantity stays fixed at two and is explained in both locales', function (): void {
    $data = customerQuantityCard('NDI-TEST-4765205551649102', 2, true);

    app()->setLocale('nb');
    $norwegian = view('storefront.catalog._card', $data)->render();
    $document = customerQuantityDocument($norwegian);
    $xpath = new DOMXPath($document);
    $input = $xpath->query('//input[@data-testid="catalog-product-quantity"]')->item(0);
    $noteId = $input->getAttribute('aria-describedby');

    expect($input->getAttribute('value'))->toBe('2')
        ->and($input->getAttribute('min'))->toBe('2')
        ->and($input->getAttribute('max'))->toBe('2')
        ->and($input->hasAttribute('readonly'))->toBeTrue()
        ->and($noteId)->not->toBe('')
        ->and($xpath->query('//*[@id="'.$noteId.'" and @data-testid="catalog-integration-test-quantity-note"]'))->toHaveCount(1)
        ->and($norwegian)->toContain('NDIs testordre krever 2 stk.')
        ->and($norwegian)->toContain('andre varer kan endres');

    app()->setLocale('ru');
    $russian = view('storefront.catalog._card', $data)->render();

    expect($russian)->toContain('Количество: Nordlys Arctic')
        ->and($russian)->toContain('Для тест-заказа NDI нужно 2 шт.')
        ->and($russian)->toContain('у обычных товаров количество меняется');
});

/** @return array{item: CatalogItem, sellingPrice: SellingPrice} */
function customerQuantityCard(string $sku, int $stock, bool $integrationTest = false): array
{
    $money = new Money(149900, 'NOK');

    return [
        'item' => new CatalogItem(
            sku: $sku,
            productType: ProductType::Tire,
            brand: 'Nordlys',
            modelName: 'Arctic',
            title: 'Nordlys Arctic',
            prices: [],
            stock: new CatalogStock($stock, now()->toIso8601String(), $stock, 0),
            tire: [
                'width_mm' => '205.00',
                'aspect_ratio' => '55.00',
                'rim_diameter_in' => '16.00',
                'load_index' => '91',
                'speed_symbol' => 'V',
                'season' => 'Winter',
            ],
            wheel: null,
            isIntegrationTest: $integrationTest,
        ),
        'sellingPrice' => new SellingPrice($money, $money, 1, 'Fixture', 'public', now()->toIso8601String()),
    ];
}

function customerQuantityDocument(string $html): DOMDocument
{
    $document = new DOMDocument;
    $document->loadHTML('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);

    return $document;
}
