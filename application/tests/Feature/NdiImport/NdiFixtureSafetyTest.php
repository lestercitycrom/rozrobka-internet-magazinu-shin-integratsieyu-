<?php

use App\Modules\NdiImport\Enums\DatasetRole;
use App\Modules\NdiImport\NdiWorkbookParser;

test('versioned NDI XLSX fixtures contain only synthetic identifiers and reserved URLs', function (): void {
    $parser = app(NdiWorkbookParser::class);
    $fixtures = glob(base_path('tests/Fixtures/Ndi/*.xlsx')) ?: [];

    expect($fixtures)->toHaveCount(6);

    foreach ($fixtures as $fixture) {
        $schema = $parser->inspect($fixture);
        $skuField = $schema->role === DatasetRole::Wheels ? 'Varenummer' : 'ItemID';

        foreach ($parser->rows($fixture, $schema) as $row) {
            $sku = $row->values[$skuField] ?? null;

            expect($sku)->toBeString()
                ->and(str_starts_with($sku, 'TEST-') || $sku === '0001234567890')->toBeTrue();

            foreach ($row->values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                expect($value)->not->toMatch('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i')
                    ->and($value)->not->toMatch('/\b(password|credential|secret|party\s*id|customer\s*number)\b/i');

                if (preg_match_all('~https?://[^\s]+~i', $value, $urls) > 0) {
                    foreach ($urls[0] as $url) {
                        expect(parse_url($url, PHP_URL_HOST))->toBe('example.invalid');
                    }
                }
            }
        }
    }
});
