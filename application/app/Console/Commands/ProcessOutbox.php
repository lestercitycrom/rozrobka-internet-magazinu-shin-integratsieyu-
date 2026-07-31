<?php

namespace App\Console\Commands;

use App\Modules\Outbox\Enums\OutboxStatus;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Outbox\ProcessOutboxMessage;
use Illuminate\Console\Command;

final class ProcessOutbox extends Command
{
    protected $signature = 'easytire:outbox {--limit=25}';

    protected $description = 'Deliver due EasyTire outbox messages without exposing their payloads.';

    public function handle(ProcessOutboxMessage $processor): int
    {
        $limit = min(100, max(1, (int) $this->option('limit')));
        $staleBefore = now()->subSeconds((int) config('outbox.lock_timeout_seconds', 300));
        $ids = OutboxMessage::query()
            ->where('available_at', '<=', now())
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', OutboxStatus::Pending->value)
                    ->orWhere(function ($stale) use ($staleBefore): void {
                        $stale->where('status', OutboxStatus::Processing->value)
                            ->where('locked_at', '<=', $staleBefore);
                    });
            })
            ->orderBy('available_at')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            if ($processor->handle((string) $id)) {
                $processed++;
            }
        }

        $this->line("processed={$processed}");

        return self::SUCCESS;
    }
}
