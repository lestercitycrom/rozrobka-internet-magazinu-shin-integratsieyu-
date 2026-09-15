<?php

use App\Modules\Outbox\Contracts\NotionOrderDestination;
use App\Modules\Outbox\Contracts\TelegramOrderDestination;
use App\Modules\Outbox\DisabledNotionOrderDestination;
use App\Modules\Outbox\DisabledTelegramOrderDestination;
use App\Modules\Outbox\HttpNotionOrderDestination;
use App\Modules\Outbox\HttpTelegramOrderDestination;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\Outbox\ProcessOutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function paidOrderPayload(): array
{
    return [
        'reference' => 'ET-PAID-123', 'amount_minor' => 129900, 'currency' => 'NOK',
        'customer' => ['name' => 'Private buyer', 'email' => 'buyer@example.test', 'phone' => '123'],
        'items' => [['sku' => 'SKU-1'], ['sku' => 'SKU-2']],
        'product_item_count' => 5, 'service_item_count' => 4,
        'product_line_count' => 2, 'service_line_count' => 1, 'line_count' => 3,
    ];
}

function configuredNotion(): void
{
    config(['outbox.notion_enabled' => true, 'outbox.notion.token' => 'notion-test-token', 'outbox.notion.database_id' => 'database-id', 'outbox.notion.title_property' => 'Name']);
}

function configuredTelegram(): void
{
    config(['outbox.telegram_enabled' => true, 'outbox.telegram.bot_token' => 'telegram-test-token', 'outbox.telegram.chat_id' => '-100123']);
}

test('disabled or incomplete configuration keeps the HTTP destinations unbound', function (): void {
    config(['outbox.notion_enabled' => true, 'outbox.notion.token' => null, 'outbox.notion.database_id' => null]);
    config(['outbox.telegram_enabled' => true, 'outbox.telegram.bot_token' => null, 'outbox.telegram.chat_id' => null]);

    expect(app(NotionOrderDestination::class))->toBeInstanceOf(DisabledNotionOrderDestination::class)
        ->and(app(TelegramOrderDestination::class))->toBeInstanceOf(DisabledTelegramOrderDestination::class);
});

test('notion adapter sends a minimal paid-order snapshot with the expected contract', function (): void {
    configuredNotion();
    Http::fake(['https://api.notion.com/v1/pages' => Http::response(['id' => 'page-id'], 200)]);

    $destination = app(NotionOrderDestination::class);
    expect($destination)->toBeInstanceOf(HttpNotionOrderDestination::class);
    $destination->deliver(paidOrderPayload(), 'notion:order-paid:1');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.notion.com/v1/pages'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer notion-test-token')
            && $request->hasHeader('Notion-Version', '2022-06-28')
            && data_get($body, 'parent.database_id') === 'database-id'
            && data_get($body, 'properties.Name.title.0.text.content') === 'EasyTire ET-PAID-123'
            && str_contains(json_encode($body, JSON_THROW_ON_ERROR), '1299.00 NOK')
            && str_contains(json_encode($body, JSON_THROW_ON_ERROR), 'Varelinjer: 2')
            && str_contains(json_encode($body, JSON_THROW_ON_ERROR), 'Tjenestelinjer: 1')
            && str_contains(json_encode($body, JSON_THROW_ON_ERROR), 'Linjer totalt: 3')
            && ! str_contains(json_encode($body, JSON_THROW_ON_ERROR), 'buyer@example.test');
    });
});

test('telegram adapter sends the minimal paid-order text contract', function (): void {
    configuredTelegram();
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $destination = app(TelegramOrderDestination::class);
    expect($destination)->toBeInstanceOf(HttpTelegramOrderDestination::class);
    $destination->deliver(paidOrderPayload(), 'telegram:order-paid:1');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.telegram.org/bottelegram-test-token/sendMessage'
            && $request->method() === 'POST'
            && $body['chat_id'] === '-100123'
            && $body['disable_web_page_preview'] === true
            && str_contains($body['text'], 'ET-PAID-123')
            && str_contains($body['text'], 'Varelinjer: 2')
            && str_contains($body['text'], 'Tjenestelinjer: 1')
            && str_contains($body['text'], 'Linjer totalt: 3')
            && ! str_contains($body['text'], 'buyer@example.test');
    });
});

test('HTTP failures remain retryable through the outbox without exposing remote details', function (): void {
    configuredTelegram();
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'remote secret'], 500)]);
    $message = OutboxMessage::create([
        'type' => 'telegram.order_paid', 'aggregate_type' => 'order', 'aggregate_id' => '1', 'idempotency_key' => 'telegram:order-paid:1',
        'payload_encrypted' => paidOrderPayload(), 'status' => 'pending', 'attempts' => 0, 'available_at' => now(),
    ]);

    expect(app(ProcessOutboxMessage::class)->handle($message->id))->toBeFalse();
    expect($message->fresh()->status->value)->toBe('pending')
        ->and($message->fresh()->attempts)->toBe(1)
        ->and($message->fresh()->last_error_code)->toBe('delivery_failed')
        ->and($message->fresh()->last_error_code)->not->toContain('remote secret');
});

test('service request outbox types deliver actionable non PII snapshots to Notion and Telegram', function (): void {
    configuredNotion();
    configuredTelegram();
    Http::fake([
        'https://api.notion.com/v1/pages' => Http::response(['id' => 'page-id'], 200),
        'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);
    $payload = [
        'event' => 'service_request_created', 'reference' => 'SR-TEST-123',
        'service_slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile',
        'preferred_date' => '2026-10-10', 'car_count' => 5, 'dekkhotell' => true,
        'quote_min_minor' => 230000, 'quote_max_minor' => 230000, 'currency' => 'NOK',
    ];
    $notion = OutboxMessage::create([
        'type' => 'notion.service_request_created', 'aggregate_type' => 'service_request', 'aggregate_id' => '1',
        'idempotency_key' => 'notion:service-request-created:1', 'payload_encrypted' => $payload,
        'status' => 'pending', 'attempts' => 0, 'available_at' => now(),
    ]);
    $telegram = OutboxMessage::create([
        'type' => 'telegram.service_request_created', 'aggregate_type' => 'service_request', 'aggregate_id' => '1',
        'idempotency_key' => 'telegram:service-request-created:1', 'payload_encrypted' => $payload,
        'status' => 'pending', 'attempts' => 0, 'available_at' => now(),
    ]);

    expect(app(ProcessOutboxMessage::class)->handle($notion->id))->toBeTrue()
        ->and(app(ProcessOutboxMessage::class)->handle($telegram->id))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.notion.com/v1/pages'
        && str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'Ny tjenesteforespørsel SR-TEST-123')
        && str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), '2300.00 NOK'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.telegram.org')
        && str_contains((string) data_get($request->data(), 'text'), 'ny tjenesteforespørsel SR-TEST-123')
        && str_contains((string) data_get($request->data(), 'text'), 'Biler: 5'));
});
