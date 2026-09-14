<?php

use App\Models\User;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
test('admin sees read only redacted NDI audit with only safe tracking links', function () {
    $o = Order::create(['id' => (string) Str::ulid(), 'public_reference' => 'ET-NDI', 'idempotency_key' => (string) Str::uuid(), 'locale' => 'nb', 'status' => OrderStatus::PendingReview, 'payment_status' => PaymentStatus::NotStarted, 'ndi_status' => NdiOrderStatus::Unknown, 'currency' => 'NOK', 'subtotal_minor' => 1, 'total_minor' => 1, 'fulfilment_method' => FulfilmentMethod::Pickup, 'customer_name' => 'C', 'customer_email' => 'c@e.test', 'customer_phone' => '1', 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(), 'pricing_snapshot_at' => now(), 'submitted_at' => now()]);
    $o->ndiOrderAttempt()->create(['id' => (string) Str::ulid(), 'customer_reference' => 'C'.Str::random(8), 'request_fingerprint' => str_repeat('a', 64), 'status' => 'accepted', 'external_order_reference' => 'EXT-1', 'error_code' => '&lt;safe&gt;', 'sales_status' => 'sale', 'document_status' => 'doc', 'carrier' => 'Post', 'tracking_summary_encrypted' => ['tracking' => [['number' => 'SECRET1234', 'url' => 'https://track.test/x'], ['number' => 'BAD5678', 'url' => 'javascript:alert(1)']]], 'attempted_at' => now()]);
    $this->actingAs(User::factory()->owner()->create())->get('/admin/orders/'.$o->id)->assertOk()->assertSee('EXT-1')->assertSee('••••••1234')->assertSee('href="https://track.test/x"', false)->assertDontSee('SECRET1234')->assertDontSee('javascript:')->assertSee('&amp;lt;safe&amp;gt;', false)->assertDontSee('Submit NDI')->assertDontSee('Reserve');
});
