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

    public function isFresh(): bool
    {
        return StockFreshnessPolicy::isFresh($this->observedAt);
    }
}
