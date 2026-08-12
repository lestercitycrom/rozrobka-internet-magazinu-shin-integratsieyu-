<?php

use App\Modules\Catalog\Support\ProductionYearNormalizer;

test('NDI DOT formats are normalized to a transparent four digit production year', function (mixed $raw, ?string $expected): void {
    expect(ProductionYearNormalizer::normalize($raw))->toBe($expected);
})->with([
    ['DOT22', '2022'],
    ['dot 21', '2021'],
    ['(-2017)', '2017'],
    ['2020', '2020'],
    ['17', '2017'],
    ['', null],
    ['not-a-year', null],
    ['DOT89', null],
]);
