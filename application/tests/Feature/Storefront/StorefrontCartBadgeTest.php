<?php

use App\Modules\Cart\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('header badge counts units, caps its visible label and retains the actual accessible total', function (int $count): void {
    $lines = [];
    for ($remaining = $count, $index = 0; $remaining > 0; $remaining -= $quantity, $index++) {
        $quantity = min(40, $remaining);
        $lines['POLISH-FIXTURE-'.$index] = $quantity;
    }

    // Session-only fixture: no catalogue, stock, cart validation or order limits are changed.
    $html = $this->withSession(['storefront.cart.lines' => $lines])->get('/')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $cart = $xpath->query('//*[@data-testid="header-cart"]')->item(0);
    $badge = $xpath->query('.//*[contains(@class, "et-cart__count")]', $cart)->item(0);

    expect($cart->getAttribute('aria-label'))->toBe(__('pro.nav.cart').': '.$count)
        ->and(trim($badge->textContent))->toBe($count > 99 ? '99+' : (string) $count)
        ->and($badge->getAttribute('aria-hidden'))->toBe('true');
})->with([0, 1, 9, 10, 99, 100, 999]);

test('header does not misrepresent an unavailable cart count as an empty cart', function (): void {
    $this->app->bind(SessionCart::class, static fn () => throw new RuntimeException('Isolated presentation failure'));

    $this->get('/')->assertOk()
        ->assertSee(__('pro.nav.cart').': '.__('pro.nav.cart_count_unavailable'))
        ->assertSee('<span class="et-cart__count" aria-hidden="true">–</span>', false);
});
