<?php

use App\Modules\Catalog\Enums\CatalogState;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierSku;
use App\Modules\Catalog\Presentation\CatalogFilterState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

test('valid absent dimensions stay selected enabled and applied in empty catalogue results', function (): void {
    consistentFilterProduct(ProductType::Tire, 'TIRE-205');

    $response = $this->get('/dekk?width=125&aspect=35&rim=13')->assertOk()
        ->assertSee('data-testid="catalog-empty"', false)
        ->assertSee('Bredde: 125')
        ->assertSee('Profil: 35')
        ->assertDontSee('data-product-sku="TIRE-205"', false);

    foreach (['width' => '125', 'aspect' => '35', 'rim' => '13'] as $field => $value) {
        expect(consistentFilterSelected($response, $field))->toBe($value);
    }
    expect($response->viewData('filters'))->toBe(['width' => '125', 'aspect' => '35', 'rim' => '13']);
    $response->assertSee('name="sort" value=""', false);
});

test('integer decimal and comma filters have the same canonical controls chips and product matches', function (string $query): void {
    consistentFilterProduct(ProductType::Tire, 'MATCH-60-20', ['aspect_ratio' => '60.00', 'rim_diameter_in' => '20.00']);
    consistentFilterProduct(ProductType::Tire, 'OTHER-55-16', ['aspect_ratio' => '55.00', 'rim_diameter_in' => '16.00']);

    $response = $this->get('/dekk?'.$query)->assertOk()
        ->assertSee('data-product-sku="MATCH-60-20"', false)
        ->assertDontSee('data-product-sku="OTHER-55-16"', false)
        ->assertSee('Profil: 60')
        ->assertSee('Felgdiameter (tommer): 20');

    expect($response->viewData('filters'))->toBe(['width' => '205', 'aspect' => '60', 'rim' => '20']);
    foreach (['width' => '205', 'aspect' => '60', 'rim' => '20'] as $field => $value) {
        expect(consistentFilterSelected($response, $field))->toBe($value);
    }
    expect($response->viewData('filterOptions')['aspects'])->toBe(['55', '60']);
})->with([
    'integers' => 'width=205&aspect=60&rim=20',
    'decimal dot' => 'width=205.00&aspect=60.00&rim=20.00',
    'comma and leading zeros' => 'width=00205%2C00&aspect=060%2C0&rim=020%2C00',
]);

test('wheel dimensions and signed offset use canonical options without losing zeros or precision', function (string $offset, string $canonicalOffset): void {
    consistentFilterProduct(ProductType::Wheel, 'MATCH-WHEEL', [
        'width_in' => '7.50', 'diameter_in' => '20.00', 'bolt_holes' => 5,
        'pcd_mm' => '110.00', 'offset_et_mm' => $offset, 'center_bore_mm' => '66.60',
    ]);
    consistentFilterProduct(ProductType::Wheel, 'OTHER-WHEEL', ['offset_et_mm' => '45.00']);

    $response = $this->get('/felger?width=7%2C50&diameter=20.00&bolt_holes=05&pcd=110.00&et='.urlencode($offset).'&center_bore=66.60')
        ->assertOk()->assertSee('data-product-sku="MATCH-WHEEL"', false)
        ->assertDontSee('data-product-sku="OTHER-WHEEL"', false)
        ->assertSee('Innpress (ET): '.$canonicalOffset);
    $expected = ['width' => '7.5', 'diameter' => '20', 'bolt_holes' => '5', 'pcd' => '110', 'et' => $canonicalOffset, 'center_bore' => '66.6'];
    expect($response->viewData('filters'))->toBe($expected);
    foreach ($expected as $field => $value) {
        expect(consistentFilterSelected($response, $field))->toBe($value);
    }
})->with(['zero' => ['0.00', '0'], 'negative' => ['-10.00', '-10']]);

test('zero offset alone really filters the database instead of only rendering a chip', function (): void {
    consistentFilterProduct(ProductType::Wheel, 'ZERO-OFFSET', ['offset_et_mm' => '0.00']);
    consistentFilterProduct(ProductType::Wheel, 'POSITIVE-OFFSET', ['offset_et_mm' => '45.00']);

    $response = $this->get('/felger?et=-0.00')->assertOk()
        ->assertSee('data-product-sku="ZERO-OFFSET"', false)
        ->assertDontSee('data-product-sku="POSITIVE-OFFSET"', false)
        ->assertSee('Innpress (ET): 0');
    expect($response->viewData('filters'))->toBe(['et' => '0'])
        ->and(consistentFilterSelected($response, 'et'))->toBe('0');
});

test('malformed irrelevant and unknown inputs are neither applied nor displayed as active filters', function (string $query): void {
    consistentFilterProduct(ProductType::Tire, 'UNFILTERED-TIRE');
    $response = $this->get('/dekk?'.$query)->assertOk()
        ->assertSee('data-product-sku="UNFILTERED-TIRE"', false)
        ->assertDontSee('data-testid="active-filters"', false);
    expect($response->viewData('filters'))->toBe([]);
})->with([
    'malformed numbers' => 'width=205oops&aspect=60.123&rim=1e1',
    'zero negative oversized dimensions' => 'width=-205&aspect=0&rim=1234567890',
    'arrays' => 'width%5B%5D=205&q%5B%5D=text&brand%5B%5D=Nordlys&season%5B%5D=Summer',
    'wheel-only fields on tires' => 'diameter=20&bolt_holes=5&pcd=110&et=0&center_bore=66.6',
    'unknown controls' => 'surprise=205&sort=bogus&in_stock=yes&has_image=2',
]);

test('tire-only inputs cannot create inactive chips on wheel results', function (): void {
    consistentFilterProduct(ProductType::Wheel, 'UNFILTERED-WHEEL');
    $response = $this->get('/felger?aspect=60&rim=20&season=Summer')->assertOk()
        ->assertSee('data-product-sku="UNFILTERED-WHEEL"', false)
        ->assertDontSee('data-testid="active-filters"', false);
    expect($response->viewData('filters'))->toBe([]);
});

test('invalid wheel inputs are removed from both applied state and controls', function (): void {
    consistentFilterProduct(ProductType::Wheel, 'VALID-WHEEL');
    $response = $this->get('/felger?width=7.500&diameter=-20&bolt_holes=5.0&pcd=0&et=--10&center_bore%5B%5D=66.6')
        ->assertOk()->assertSee('data-product-sku="VALID-WHEEL"', false)
        ->assertDontSee('data-testid="active-filters"', false);
    expect($response->viewData('filters'))->toBe([]);
});

test('missing tire profile zero is not offered as a meaningful dimension', function (): void {
    $state = CatalogFilterState::fromRequest(Request::create('/dekk', 'GET', ['aspect' => '0']), ProductType::Tire);
    expect($state->filters)->toBe([])
        ->and($state->query()->tireAspectRatio)->toBeNull()
        ->and($state->options(['aspects' => ['0.00', '60.00']])['aspects'])->toBe(['60']);
});

test('unknown requested brand and season remain selected enabled and safely escaped', function (): void {
    consistentFilterProduct(ProductType::Tire, 'NOT-A-MATCH');
    $brand = '<script>alert("brand")</script>';
    $season = 'Unknown & seasonal';
    $response = $this->get('/dekk?'.http_build_query(['brand' => $brand, 'season' => $season]))->assertOk()
        ->assertDontSee($brand, false)
        ->assertSee(e($brand), false)
        ->assertSee('data-testid="catalog-empty"', false);
    expect(consistentFilterSelected($response, 'brand'))->toBe($brand)
        ->and(consistentFilterSelected($response, 'season'))->toBe($season)
        ->and($response->viewData('filters'))->toBe(['brand' => $brand, 'season' => $season]);
});

test('text zero is a real search brand or season value rather than a falsey omitted condition', function (): void {
    consistentFilterProduct(ProductType::Tire, '0');
    consistentFilterProduct(ProductType::Tire, 'SKU-OTHER');
    $this->get('/dekk?q=0')->assertOk()
        ->assertSee('data-product-sku="0"', false)
        ->assertDontSee('data-product-sku="SKU-OTHER"', false)
        ->assertSee('Søk: 0');
    foreach (['brand', 'season'] as $field) {
        $response = $this->get('/dekk?'.$field.'=0')->assertOk()
            ->assertSee('data-testid="catalog-empty"', false)
            ->assertDontSee('data-product-sku=', false);
        expect(consistentFilterSelected($response, $field))->toBe('0');
    }
});

test('canonical state supplies the same normalized values to query options and view filters', function (): void {
    $state = CatalogFilterState::fromRequest(Request::create('/felger', 'GET', [
        'width' => ' 7,50 ', 'diameter' => '20.00', 'bolt_holes' => '005', 'et' => '-0',
        'page' => '002', 'sort' => 'brand', 'in_stock' => '1', 'has_image' => '1', 'aspect' => '60',
    ]), ProductType::Wheel);
    $query = $state->query();
    expect($state->filters)->toBe([
        'width' => '7.5', 'diameter' => '20', 'bolt_holes' => '5', 'et' => '0',
        'in_stock' => '1', 'has_image' => '1', 'sort' => 'brand',
    ])->and($query->wheelWidthIn)->toBe('7.5')
        ->and($query->wheelDiameterIn)->toBe('20')
        ->and($query->wheelBoltHoles)->toBe(5)
        ->and($query->wheelOffsetEtMm)->toBe('0')
        ->and($query->tireAspectRatio)->toBeNull()
        ->and($query->page)->toBe(2)
        ->and($query->sort)->toBe('brand')
        ->and($query->inStockOnly)->toBeTrue()
        ->and($query->hasImageOnly)->toBeTrue()
        ->and($state->options(['widths' => ['7.50', '7.5', '8.00']])['widths'])->toBe(['7.5', '8']);
});

test('canonical sort is always available in the filter form and invalid sort clears it', function (): void {
    foreach (['title' => 'title', 'brand' => 'brand', 'invalid' => ''] as $requested => $expected) {
        $response = $this->get('/dekk?sort='.$requested)->assertOk();
        $response->assertSee('type="hidden" name="sort" value="'.$expected.'"', false);
    }
});

function consistentFilterSelected(TestResponse $response, string $field): string
{
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $selected = $xpath->query('//form[@data-testid="catalog-filters"]//select[@name="'.$field.'"]/option[@selected]');
    expect($selected)->toHaveCount(1);
    $option = $selected->item(0);
    expect($option->hasAttribute('disabled'))->toBeFalse();

    return $option->getAttribute('value');
}

function consistentFilterProduct(ProductType $type, string $code, array $spec = []): SupplierSku
{
    $supplier = Supplier::firstOrCreate(['code' => 'filter-ndi'], ['name' => 'Filter NDI', 'is_active' => true]);
    $brand = Brand::firstOrCreate(['canonical_name' => 'Nordlys'], ['slug' => 'nordlys']);
    $product = Product::create([
        'product_type' => $type, 'brand_id' => $brand->id, 'model_name' => 'Arctic',
        'title' => 'Nordlys Arctic', 'publication_status' => PublicationStatus::Published, 'first_seen_at' => now(),
    ]);
    if ($type === ProductType::Tire) {
        $product->tireSpec()->create([
            'width_mm' => '205.00', 'aspect_ratio' => '60.00', 'rim_diameter_in' => '16.00',
            'load_index' => '91', 'speed_symbol' => 'V', 'season' => 'Summer', ...$spec,
        ]);
    } else {
        $product->wheelSpec()->create([
            'width_in' => '7.50', 'diameter_in' => '20.00', 'bolt_holes' => 5,
            'pcd_mm' => '110.00', 'offset_et_mm' => '45.00', 'center_bore_mm' => '66.60', ...$spec,
        ]);
    }

    return SupplierSku::create([
        'supplier_id' => $supplier->id, 'product_id' => $product->id,
        'external_sku' => $code, 'catalog_state' => CatalogState::Catalog,
    ]);
}
