<?php

namespace App\Modules\Outbox\Contracts;

interface NotionOrderDestination
{
    public function deliver(array $payload, string $idempotencyKey): void;
}
