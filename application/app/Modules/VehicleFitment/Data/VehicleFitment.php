<?php

namespace App\Modules\VehicleFitment\Data;

final readonly class VehicleFitment
{
    /** @param list<array{width:string,aspect:string,rim:string}> $tyres */
    public function __construct(public string $make, public string $model, public array $tyres) {}
}
