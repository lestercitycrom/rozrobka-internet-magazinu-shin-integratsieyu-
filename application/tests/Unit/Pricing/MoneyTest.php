<?php

use App\Modules\Pricing\ValueObjects\Money;

test('money parses source decimals without floating point arithmetic', function (): void {
    expect(Money::fromDecimal('1499.9949', 'NOK')->minor)->toBe(149999)
        ->and(Money::fromDecimal('1499.9950', 'NOK')->minor)->toBe(150000)
        ->and(Money::fromDecimal('0.0049', 'NOK')->minor)->toBe(0)
        ->and(Money::fromDecimal('0.0050', 'NOK')->minor)->toBe(1);
});

test('money applies basis points fixed markup and upward price rounding exactly', function (): void {
    $price = Money::fromDecimal('1000.00', 'NOK')
        ->addBasisPoints(1250)
        ->addMinor(2500)
        ->roundUpTo(100);

    expect($price->minor)->toBe(115000)
        ->and($price->decimal())->toBe('1150.00')
        ->and($price->multiply(4)->minor)->toBe(460000);
});

test('money rejects invalid decimals and negative results', function (): void {
    expect(fn () => Money::fromDecimal('10,00', 'NOK'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::fromDecimal('1e3', 'NOK'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Money(100, 'NOK'))->addMinor(-101))->toThrow(InvalidArgumentException::class);
});
