<?php

namespace App\Modules\Outbox;

use App\Modules\Outbox\Contracts\TelegramOrderDestination;

final class DisabledTelegramOrderDestination implements TelegramOrderDestination
{
    public function deliver(array $payload, string $idempotencyKey): void
    {
        throw new \RuntimeException('disabled');
    }
}
