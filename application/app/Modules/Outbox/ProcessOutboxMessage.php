<?php

namespace App\Modules\Outbox;

use App\Modules\Outbox\Contracts\NotionOrderDestination;
use App\Modules\Outbox\Contracts\TelegramOrderDestination;
use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessOutboxMessage
{
    public function __construct(
        private readonly NotionOrderDestination $notion,
        private readonly TelegramOrderDestination $telegram,
    ) {}

    public function handle(string $id): bool
    {
        $message = $this->claim($id);
        if ($message === null) {
            return false;
        }

        try {
            $this->destination($message)->deliver($message->payload_encrypted, $message->idempotency_key);
        } catch (Throwable) {
            $this->markFailure($message);

            return false;
        }

        return $this->markDelivered($message);
    }

    private function claim(string $id): ?OutboxMessage
    {
        return DB::transaction(function () use ($id): ?OutboxMessage {
            $message = OutboxMessage::query()->lockForUpdate()->find($id);
            if ($message === null || $message->status === OutboxStatus::Delivered || $message->available_at->isFuture()) {
                return null;
            }

            $staleBefore = now()->subSeconds((int) config('outbox.lock_timeout_seconds', 300));
            if ($message->locked_at !== null && $message->locked_at->greaterThan($staleBefore)) {
                return null;
            }

            if (! in_array($message->type, ['notion.order_paid', 'telegram.order_paid'], true)) {
                $message->update(['status' => OutboxStatus::Failed, 'locked_at' => null, 'last_error_code' => 'unknown_type']);

                return null;
            }

            $channel = str_starts_with($message->type, 'notion.') ? 'notion' : 'telegram';
            if (! config("outbox.{$channel}_enabled")) {
                $message->update([
                    'status' => OutboxStatus::Pending,
                    'locked_at' => null,
                    'available_at' => now()->addMinutes(5),
                    'last_error_code' => 'destination_disabled',
                ]);

                return null;
            }

            if ($message->attempts >= (int) config('outbox.max_attempts', 5)) {
                $message->update(['status' => OutboxStatus::Failed, 'locked_at' => null, 'last_error_code' => 'attempts_exhausted']);

                return null;
            }

            $message->update([
                'status' => OutboxStatus::Processing,
                'locked_at' => now(),
                'attempts' => $message->attempts + 1,
                'last_error_code' => null,
            ]);

            return $message->refresh();
        }, 3);
    }

    private function destination(OutboxMessage $message): NotionOrderDestination|TelegramOrderDestination
    {
        return str_starts_with($message->type, 'notion.') ? $this->notion : $this->telegram;
    }

    private function markDelivered(OutboxMessage $claimed): bool
    {
        return DB::transaction(function () use ($claimed): bool {
            $message = OutboxMessage::query()->lockForUpdate()->find($claimed->id);
            if ($message === null || $message->status !== OutboxStatus::Processing) {
                return false;
            }

            $message->update([
                'status' => OutboxStatus::Delivered,
                'delivered_at' => now(),
                'locked_at' => null,
                'last_error_code' => null,
            ]);

            return true;
        }, 3);
    }

    private function markFailure(OutboxMessage $claimed): void
    {
        DB::transaction(function () use ($claimed): void {
            $message = OutboxMessage::query()->lockForUpdate()->find($claimed->id);
            if ($message === null || $message->status !== OutboxStatus::Processing) {
                return;
            }

            $failed = $message->attempts >= (int) config('outbox.max_attempts', 5);
            $message->update([
                'status' => $failed ? OutboxStatus::Failed : OutboxStatus::Pending,
                'locked_at' => null,
                'available_at' => now()->addMinutes(min(60, 2 ** $message->attempts)),
                'last_error_code' => 'delivery_failed',
            ]);
        }, 3);
    }
}
