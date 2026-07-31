<?php

namespace App\Modules\Cart;

use Illuminate\Contracts\Session\Session;

final class SessionCart
{
    private const KEY = 'storefront.cart.lines';

    public function __construct(private readonly Session $session) {}

    /** @return array<string, int> */
    public function items(): array
    {
        $stored = $this->session->get(self::KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $items = [];
        foreach ($stored as $sku => $quantity) {
            if (! is_string($sku) || preg_match('/^[A-Za-z0-9._-]{1,80}$/', $sku) !== 1) {
                continue;
            }

            $normalized = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($normalized !== false && $normalized >= 1 && $normalized <= 40) {
                $items[$sku] = $normalized;
            }
        }

        return $items;
    }

    public function put(string $sku, int $quantity): void
    {
        $items = $this->items();
        $items[$sku] = $quantity;
        $this->session->put(self::KEY, $items);
    }

    public function add(string $sku, int $quantity): void
    {
        $items = $this->items();
        $items[$sku] = min(40, ($items[$sku] ?? 0) + $quantity);
        $this->session->put(self::KEY, $items);
    }

    public function remove(string $sku): void
    {
        $items = $this->items();
        unset($items[$sku]);
        $this->session->put(self::KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
