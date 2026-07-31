<?php

use App\Models\User;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\TransitionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('guests and customers cannot access order administration', function (): void {
    $this->get('/admin/orders')->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())->get('/admin/orders')->assertForbidden();
});

test('admin filters orders, sees detail snapshots, and dashboard exposes no customer pii', function (): void {
    $admin = User::factory()->operations()->create();
    $order = adminOrder(['public_reference' => 'ET-FILTER', 'customer_email' => 'person@example.test']);
    $order->items()->create(['external_sku' => '001', 'product_title' => 'Tyre', 'quantity' => 1, 'unit_price_minor' => 100, 'line_total_minor' => 100, 'currency' => 'NOK', 'product_snapshot' => ['brand' => 'B'], 'pricing_snapshot' => ['rule_name' => 'Rule']]);
    $this->actingAs($admin)->get('/admin/orders?q=ET-FILTER&status=pending_review')->assertOk()->assertSee('ET-FILTER')->assertSee('Til gjennomgang')->assertSee('Ikke startet')->assertSee('Ikke sendt');
    $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Immutable items and pricing snapshot')->assertSee('NDI reserve/cancel unavailable')->assertDontSee('Mark paid')->assertDontSee('Submit NDI')->assertDontSee('product_snapshot')->assertDontSee('<pre>', false);
    $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('person@example.test')->assertDontSee('ET-FILTER');
});

test('invalid order filters are rejected safely', function (): void {
    $this->actingAs(User::factory()->operations()->create())->get('/admin/orders?status=invalid')->assertStatus(422);
});

test('valid enum order filter is applied', function (): void {
    $admin = User::factory()->operations()->create();
    adminOrder(['public_reference' => 'ET-VALID']);
    $this->actingAs($admin)->get('/admin/orders?status=pending_review')->assertOk()->assertSee('ET-VALID')->assertSee('Til gjennomgang');
});

test('admin can execute an allowed transition and history is retained', function (): void {
    $admin = User::factory()->operations()->create();
    $order = adminOrder();
    $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/transition', ['status' => 'awaiting_payment', 'note' => 'Reviewed'])->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)->and($order->statusHistory()->count())->toBe(1);
});

test('cancelled and failed transitions require notes and invalid transitions are rejected', function (): void {
    $admin = User::factory()->operations()->create();
    $order = adminOrder();
    $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/transition', ['status' => 'cancelled'])->assertSessionHasErrors('note');
    expect(fn () => app(TransitionOrder::class)->execute($order, OrderStatus::Fulfilled, $admin, 'no'))
        ->toThrow(InvalidOrderTransition::class);
});

function adminOrder(array $overrides = []): Order
{
    return Order::create([...['id' => (string) Str::ulid(), 'public_reference' => 'ET-'.Str::upper(Str::random(8)), 'idempotency_key' => (string) Str::uuid(), 'locale' => 'nb', 'status' => OrderStatus::PendingReview, 'payment_status' => PaymentStatus::NotStarted, 'ndi_status' => NdiOrderStatus::NotSubmitted, 'currency' => 'NOK', 'subtotal_minor' => 100, 'total_minor' => 100, 'fulfilment_method' => FulfilmentMethod::Pickup, 'customer_name' => 'Customer', 'customer_email' => 'customer@example.test', 'customer_phone' => '123', 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(), 'pricing_snapshot_at' => now(), 'submitted_at' => now()], ...$overrides]);
}
