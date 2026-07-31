<?php

namespace App\Modules\Outbox;

use App\Modules\Outbox\Contracts\NotionOrderDestination;

final class DisabledNotionOrderDestination implements NotionOrderDestination
{
    public function deliver(array $payload, string $idempotencyKey): void
    {
        throw new \RuntimeException('disabled');
    }
}
