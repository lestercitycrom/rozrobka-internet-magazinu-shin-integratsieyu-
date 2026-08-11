<?php

use App\Modules\NdiImport\Enums\DatasetRole;
use App\Modules\NdiImport\NdiWorkbookParser;
use App\Modules\NdiImport\NdiWorkbookValidator;

test('NDI workbook roles are detected from exact headers instead of filenames', function (string $fixture, DatasetRole $role): void {
    $schema = app(NdiWorkbookParser::class)->inspect(ndiFixture($fixture));

    expect($schema->role)->toBe($role)
        ->and($schema->sheetName)->toBe('Article');
})->with([
    ['tires_valid.xlsx', DatasetRole::Tires],
    ['wheels_valid.xlsx', DatasetRole::Wheels],
    ['stock_all_warehouses.xlsx', DatasetRole::Stock],
]);

test('valid tire rows preserve a numeric-looking SKU and normalize prices', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('tires_valid.xlsx'));
    $rows = iterator_to_array($result->rows(), false);

    expect($result->isValid())->toBeTrue()
        ->and($result->acceptedCount)->toBe(3)
        ->and($result->rejections)->toBeEmpty()
        ->and($rows[0]['external_sku'])->toBe('0001234567890')
        ->and(strlen($rows[0]['external_sku']))->toBe(13)
        ->and($rows[0]['prices'])->toHaveKeys(['gross', 'net']);
});

test('an explicit empty NDI image id is accepted as no product image', function (): void {
    $fixture = ndiTireFixtureWithFirstMediaUrl('https://example.invalid/Imagev2.aspx?id=');

    try {
        $result = app(NdiWorkbookValidator::class)->validate($fixture);
        $rows = iterator_to_array($result->rows(), false);

        expect($result->isValid())->toBeTrue()
            ->and($rows[0]['media_url'])->toBeNull();
    } finally {
        @unlink($fixture);
    }
});

test('an unrecognized nonempty NDI image URL quarantines the workbook row', function (): void {
    $fixture = ndiTireFixtureWithFirstMediaUrl('https://example.invalid/Imagev3.aspx?id=11111111-1111-4111-8111-111111111111');

    try {
        $result = app(NdiWorkbookValidator::class)->validate($fixture);

        expect($result->isValid())->toBeFalse()
            ->and($result->acceptedCount)->toBe(0)
            ->and($result->rejections)->toHaveCount(1)
            ->and($result->rejections[0]->fieldName)->toBe('PlainUrl')
            ->and($result->rejections[0]->reason)->toBe('unrecognized_non_empty_ndi_media_url');
    } finally {
        @unlink($fixture);
    }
});

test('valid wheel rows retain zero prices as data', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('wheels_valid.xlsx'));
    $rows = collect(iterator_to_array($result->rows(), false))->keyBy('external_sku');

    expect($result->acceptedCount)->toBe(3)
        ->and($rows['TEST-WHEEL-003']['prices']['gross'])->toBe('0')
        ->and($rows['TEST-WHEEL-003']['prices']['net'])->toBe('0');
});

test('updated wheel workbooks accept the NDI PlainUrl media column', function (string $header): void {
    $url = 'https://files.ndias.com/Imagev2.aspx?id=77777777-7777-4777-8777-777777777777';
    $fixture = ndiWheelFixtureWithMediaColumn($header, $url);
    config()->set('ndi.media_hosts', ['files.ndias.com']);

    try {
        $result = app(NdiWorkbookValidator::class)->validate($fixture);
        $rows = iterator_to_array($result->rows(), false);

        expect($result->rejections)->toBeEmpty()
            ->and($result->isValid())->toBeTrue()
            ->and($result->acceptedCount)->toBe(3)
            ->and($rows[0]['media_url'])->toBe($url)
            ->and($rows[1]['media_url'])->toBeNull();
    } finally {
        @unlink($fixture);
    }
})->with(['PlainUrl', 'PlainURL']);

test('valid stock rows retain all warehouse observations and check the capped national availability', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('stock_all_warehouses.xlsx'));
    $rows = iterator_to_array($result->rows(), false);

    expect($result->acceptedCount)->toBe(7)
        ->and($rows)->toHaveCount(7)
        ->and($rows[0]['locations'])->toHaveCount(7)
        ->and(collect($rows)->sum(fn (array $row): int => count($row['locations'])))->toBe(49)
        ->and(collect($rows)->contains(function (array $row): bool {
            $locations = $row['locations'];
            $locationTotal = array_sum(array_diff_key($locations, ['norway' => true]));

            return $locationTotal > 40 && $locations['norway'] === 40;
        }))->toBeTrue();
});

test('duplicate SKU invalidates the entire dataset and identifies both Excel rows', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('duplicate_sku.xlsx'));

    expect($result->isValid())->toBeFalse()
        ->and($result->acceptedCount)->toBe(0)
        ->and($result->rejections)->toHaveCount(1)
        ->and($result->rejections[0]->rowNumber)->toBe(3)
        ->and($result->rejections[0]->fieldName)->toBe('ItemID')
        ->and($result->rejections[0]->reason)->toBe('duplicate_external_sku:first_row=2');
});

test('invalid decimal identifies the exact Excel row and field without publishing candidates', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('invalid_decimal.xlsx'));

    expect($result->acceptedCount)->toBe(0)
        ->and($result->rejections)->toHaveCount(1)
        ->and($result->rejections[0]->rowNumber)->toBe(2)
        ->and($result->rejections[0]->fieldName)->toBe('Net_Price');
});

test('negative stock identifies the exact Excel row and field without changing state', function (): void {
    $result = app(NdiWorkbookValidator::class)->validate(ndiFixture('negative_stock.xlsx'));

    expect($result->acceptedCount)->toBe(0)
        ->and($result->rejections)->toHaveCount(1)
        ->and($result->rejections[0]->rowNumber)->toBe(2)
        ->and($result->rejections[0]->fieldName)->toBe('InStockNorway');
});

function ndiTireFixtureWithFirstMediaUrl(string $url): string
{
    $path = tempnam(sys_get_temp_dir(), 'ndi-media-url-');

    if ($path === false || ! copy(ndiFixture('tires_valid.xlsx'), $path)) {
        throw new RuntimeException('Unable to create a temporary NDI workbook fixture.');
    }

    $zip = new ZipArchive;

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the temporary NDI workbook fixture.');
    }

    $entry = 'xl/worksheets/sheet1.xml';
    $xml = $zip->getFromName($entry);

    if (! is_string($xml)) {
        $zip->close();
        throw new RuntimeException('Unable to read the temporary NDI worksheet.');
    }

    $replacement = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $updated = preg_replace(
        '~https://example\.invalid/Imagev2\.aspx\?id=11111111-1111-4111-8111-111111111111~',
        $replacement,
        $xml,
        1,
    );

    if (! is_string($updated) || $updated === $xml || ! $zip->addFromString($entry, $updated)) {
        $zip->close();
        throw new RuntimeException('Unable to update the temporary NDI worksheet.');
    }

    $zip->close();

    return $path;
}

function ndiWheelFixtureWithMediaColumn(string $header, string $url): string
{
    $path = tempnam(sys_get_temp_dir(), 'ndi-wheel-media-');

    if ($path === false || ! copy(ndiFixture('wheels_valid.xlsx'), $path)) {
        throw new RuntimeException('Unable to create a temporary NDI wheel workbook fixture.');
    }

    $zip = new ZipArchive;

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the temporary NDI wheel workbook fixture.');
    }

    $entry = 'xl/worksheets/sheet1.xml';
    $xml = $zip->getFromName($entry);

    if (! is_string($xml)) {
        $zip->close();
        throw new RuntimeException('Unable to read the temporary NDI wheel worksheet.');
    }

    $headerCell = '<x:c r="O1" s="7" t="str"><x:v>'.htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</x:v></x:c>';
    $urlCell = '<x:c r="O2" s="10" t="str"><x:v>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</x:v></x:c>';
    $updated = preg_replace('~(<x:row r="1"[^>]*>.*?)(</x:row>)~s', '$1'.$headerCell.'$2', $xml, 1);
    $updated = is_string($updated)
        ? preg_replace('~(<x:row r="2"[^>]*>.*?)(</x:row>)~s', '$1'.$urlCell.'$2', $updated, 1)
        : null;

    if (! is_string($updated) || $updated === $xml || ! $zip->addFromString($entry, $updated)) {
        $zip->close();
        throw new RuntimeException('Unable to add PlainUrl to the temporary NDI wheel worksheet.');
    }

    $zip->close();

    return $path;
}
