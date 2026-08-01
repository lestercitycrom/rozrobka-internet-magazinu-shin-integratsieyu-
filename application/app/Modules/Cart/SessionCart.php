<?php

namespace App\Modules\Cart;

use Illuminate\Contracts\Session\Session;

final class SessionCart
{
    private const KEY = 'storefront.cart.lines';

    private const SERVICE_KEY = 'storefront.cart.service_addons';

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

    /** @return list<string> */
    public function serviceAddons(): array
    {
        $stored = $this->session->get(self::SERVICE_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        return collect($stored)
            ->filter(fn ($code): bool => is_string($code) && preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $code) === 1)
            ->unique()
            ->values()
            ->all();
    }

    public function addServiceAddon(string $code): void
    {
        $selected = $this->serviceAddons();
        if (! in_array($code, $selected, true)) {
            $this->session->put(self::SERVICE_KEY, [...$selected, $code]);
        }
    }

    public function removeServiceAddon(string $code): void
    {
        $this->session->put(
            self::SERVICE_KEY,
            array_values(array_filter($this->serviceAddons(), fn (string $selected): bool => $selected !== $code)),
        );
    }

    public function clear(): void
    {
        $this->session->forget([self::KEY, self::SERVICE_KEY]);
    }
}
