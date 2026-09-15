<?php

namespace App\Modules\Catalog\ReadModel;

/** Snapshot-derived stock only; it is not an availability or delivery promise. */
final readonly class CatalogStock
{
    public function __construct(
        public int $quantity,
        public ?string $observedAt,
        public int $localQuantity = 0,
        public int $remoteQuantity = 0,
    ) {}

    public function isAvailableLocally(): bool
    {
        return $this->localQuantity > 0;
    }

    public function hasSnapshot(): bool
    {
        return $this->observedAt !== null && trim($this->observedAt) !== '';
    }

    public function isFresh(): bool
    {
        return StockFreshnessPolicy::isFresh($this->observedAt);
    }

    public function state(): CatalogStockState
    {
        if (! $this->hasSnapshot()) {
            return CatalogStockState::Missing;
        }
        if (! $this->isFresh()) {
            return CatalogStockState::Stale;
        }
        if ($this->quantity < 1) {
            return CatalogStockState::OutOfStock;
        }

        return $this->isAvailableLocally() ? CatalogStockState::Local : CatalogStockState::Remote;
    }

    public function canPurchase(int $requestedQuantity = 1): bool
    {
        return $requestedQuantity > 0
            && $this->isFresh()
            && $this->quantity >= $requestedQuantity;
    }
}
