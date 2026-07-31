<?php

use App\Modules\Outbox\Contracts\NotionOrderDestination;
use App\Modules\Outbox\Contracts\TelegramOrderDestination;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Outbox\ProcessOutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function outboxMessage(string $type = 'notion.order_paid'): OutboxMessage
{
    return OutboxMessage::create([
        'type' => $type,
        'aggregate_type' => 'order',
        'aggregate_id' => '1',
        'idempotency_key' => (string) str()->uuid(),
        'payload_encrypted' => ['email' => 'secret@example.test'],
        'status' => 'pending',
        'attempts' => 0,
        'available_at' => now(),
    ]);
}

test('outbox delivers once outside the claim transaction and skips a delivered duplicate', function (): void {
    config(['outbox.notion_enabled' => true]);
    $notion = new class implements NotionOrderDestination
    {
        public int $calls = 0;

        public function deliver(array $payload, string $idempotencyKey): void
        {
            $this->calls++;
        }
    };
    app()->instance(NotionOrderDestination::class, $notion);

    $message = outboxMessage();
    expect(app(ProcessOutboxMessage::class)->handle($message->id))->toBeTrue()
        ->and(app(ProcessOutboxMessage::class)->handle($message->id))->toBeFalse()
        ->and($notion->calls)->toBe(1)
        ->and($message->fresh()->status->value)->toBe('delivered');
});

test('disabled and unknown destinations never expose or deliver the payload', function (): void {
    $notion = new class implements NotionOrderDestination
    {
        public int $calls = 0;

        public function deliver(array $payload, string $idempotencyKey): void
        {
            $this->calls++;
        }
    };
    app()->instance(NotionOrderDestination::class, $notion);

    $disabled = outboxMessage();
    app(ProcessOutboxMessage::class)->handle($disabled->id);
    $unknown = outboxMessage('bad.type');
    app(ProcessOutboxMessage::class)->handle($unknown->id);

    expect($notion->calls)->toBe(0)
        ->and($disabled->fresh()->last_error_code)->toBe('destination_disabled')
        ->and($unknown->fresh()->last_error_code)->toBe('unknown_type')
        ->and($disabled->fresh()->getRawOriginal('payload_encrypted'))->not->toContain('secret@example.test');
});

test('a freshly locked message is not modified by a second worker', function (): void {
    $message = outboxMessage();
    $message->update(['locked_at' => now(), 'last_error_code' => 'existing_lock']);

    expect(app(ProcessOutboxMessage::class)->handle($message->id))->toBeFalse();
    expect($message->fresh()->attempts)->toBe(0)
        ->and($message->fresh()->last_error_code)->toBe('existing_lock')
        ->and($message->fresh()->locked_at)->not->toBeNull();
});

test('delivery exception is redacted and scheduled with backoff', function (): void {
    config(['outbox.telegram_enabled' => true, 'outbox.max_attempts' => 3]);
    app()->instance(TelegramOrderDestination::class, new class implements TelegramOrderDestination
    {
        public function deliver(array $payload, string $idempotencyKey): void
        {
            throw new RuntimeException('secret@example.test remote body and token');
        }
    });

    $message = outboxMessage('telegram.order_paid');
    expect(app(ProcessOutboxMessage::class)->handle($message->id))->toBeFalse();

    $message->refresh();
    expect($message->status->value)->toBe('pending')
        ->and($message->attempts)->toBe(1)
        ->and($message->last_error_code)->toBe('delivery_failed')
        ->and($message->available_at->isFuture())->toBeTrue()
        ->and((string) $message->last_error_code)->not->toContain('secret@example.test');
});
