<?php

namespace App\Modules\Outbox\Contracts;

interface TelegramOrderDestination
{
    public function deliver(array $payload, string $idempotencyKey): void;
}
