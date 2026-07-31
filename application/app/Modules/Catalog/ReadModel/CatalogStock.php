<?php

namespace App\Modules\Catalog\ReadModel;

/** Snapshot-derived stock only; it is not an availability or delivery promise. */
final readonly class CatalogStock
{
    public function __construct(public int $quantity, public ?string $observedAt) {}
}
