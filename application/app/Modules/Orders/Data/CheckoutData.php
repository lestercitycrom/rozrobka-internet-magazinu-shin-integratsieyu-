<?php

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Enums\FulfilmentMethod;

final readonly class CheckoutData
{
    public function __construct(
        public string $idempotencyKey,
        public string $locale,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public FulfilmentMethod $fulfilmentMethod,
        public ?string $vehicleRegistration,
        public ?string $customerNote,
        /** @var array{source:string,width:string,aspect:string,rim:string,confirmed_at:string}|null */
        public ?array $fitmentSnapshot = null,
        public bool $fitmentConfirmed = false,
        public bool $paymentObligationConfirmed = false,
        public bool $earlyServiceStartRequested = false,
    ) {}
}
