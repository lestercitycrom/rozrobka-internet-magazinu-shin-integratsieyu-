<?php

use Illuminate\Support\Facades\Blade;

test('storefront money presents NOK as kr and preserves decimal and unknown currency contracts', function (): void {
    $text = static fn (string $html): string => preg_replace('/\s+/u', ' ', trim(strip_tags($html)));

    expect($text(Blade::render('<x-storefront-money :minor="123400" currency="NOK" :decimals="0" />')))
        ->toBe('1 234 kr')
        ->and($text(Blade::render('<x-storefront-money :minor="123456" currency="NOK" />')))
        ->toBe('1 234,56 kr')
        ->and($text(Blade::render('<x-storefront-money :minor="123456" currency="EUR" />')))
        ->toBe('1 234,56 EUR')
        ->and($text(Blade::render('<x-storefront-money :minor="123456" currency="eur" />')))
        ->toBe('1 234,56 eur')
        ->and($text(Blade::render('<x-storefront-money :minor="123456" currency="nok" />')))
        ->toBe('1 234,56 kr');
});
