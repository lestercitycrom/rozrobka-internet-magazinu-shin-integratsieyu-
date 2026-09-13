<?php

use App\Models\User;
use App\Modules\Catalog\Enums\TaxMode;
use App\Modules\Pricing\Enums\PricingRuleScope;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins create pricing rules with exact input conversions and scope rules', function (): void {
    $admin = User::factory()->owner()->create();
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['source_tax_mode' => 'net', 'markup_percent' => '12.34', 'fixed_markup_nok' => '5.50', 'rounding_nok' => '1.00']))->assertRedirect();
    $rule = PricingRule::firstOrFail();
    expect($rule->source_tax_mode)->toBe(TaxMode::Net)->and($rule->markup_basis_points)->toBe(1234)->and($rule->fixed_markup_minor)->toBe(550)->and($rule->rounding_increment_minor)->toBe(100)->and($rule->scope_key)->toBeNull();
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['scope' => 'brand', 'scope_key' => '']))->assertSessionHasErrors('scope_key');
});
test('active overlapping rules are rejected but inactive and nonoverlapping rules are allowed', function (): void {
    $admin = User::factory()->owner()->create();
    $payload = pricingPayload(['is_active' => '1', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
    $this->actingAs($admin)->post('/admin/pricing-rules', $payload)->assertRedirect();
    $this->actingAs($admin)->post('/admin/pricing-rules', $payload)->assertSessionHasErrors('priority');
    $this->actingAs($admin)->post('/admin/pricing-rules', [...$payload, 'source_tax_mode' => 'net'])->assertRedirect();
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['is_active' => '1', 'starts_at' => '2027-01-01', 'ends_at' => '2027-12-31']))->assertRedirect();
});

test('pricing rule source tax mode is required and enum validated', function (array $override): void {
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->post('/admin/pricing-rules', pricingPayload($override))
        ->assertSessionHasErrors('source_tax_mode');

    expect(PricingRule::query()->count())->toBe(0);
})->with([
    'missing' => [['source_tax_mode' => null]],
    'unknown' => [['source_tax_mode' => 'including-vat']],
]);
test('guests and customers cannot manage pricing rules', function (): void {
    $this->get('/admin/pricing-rules')->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())->get('/admin/pricing-rules')->assertForbidden();
});

test('pricing rule inputs reject negative and excessive adjustments', function (): void {
    $admin = User::factory()->owner()->create();

    foreach ([
        ['markup_percent' => '-0.01'],
        ['markup_percent' => '500.01'],
        ['fixed_markup_nok' => '-1'],
        ['fixed_markup_nok' => '1000000.01'],
        ['rounding_nok' => '10000.01'],
    ] as $invalid) {
        $field = array_key_first($invalid);
        $this->actingAs($admin)
            ->post('/admin/pricing-rules', pricingPayload($invalid))
            ->assertSessionHasErrors($field);
    }

    expect(PricingRule::query()->count())->toBe(0);
});

test('pricing safety errors use the administrators Russian locale', function (): void {
    $admin = User::factory()->owner()->create(['locale' => 'ru']);

    $this->actingAs($admin)
        ->post('/admin/pricing-rules', pricingPayload(['rounding_nok' => '0']))
        ->assertSessionHasErrors(['rounding_nok' => 'Шаг округления должен быть не меньше 0,01 NOK.']);
});

test('pricing rule screens expose accessible controls and responsive status presentation', function (): void {
    $admin = User::factory()->owner()->create();
    PricingRule::create(['name' => 'Active retail', 'scope' => PricingRuleScope::Global, 'source_price_list_code' => 'ndi-file-current', 'currency' => 'NOK', 'markup_basis_points' => 1000, 'fixed_markup_minor' => 0, 'rounding_increment_minor' => 100, 'priority' => 1, 'is_active' => true]);

    $this->actingAs($admin)->get('/admin/pricing-rules')
        ->assertOk()
        ->assertSee('easytire-card', false)
        ->assertSee('et-admin-status--success', false)
        ->assertSee('Active retail');

    $this->actingAs($admin)->get('/admin/pricing-rules/create')
        ->assertOk()
        ->assertSee('for="name"', false)
        ->assertSee('id="scope"', false)
        ->assertSee('id="source_tax_mode"', false)
        ->assertSee('value="gross"', false)
        ->assertSee('value="net"', false)
        ->assertSee('type="checkbox"', false)
        ->assertSee('type="date"', false)
        ->assertSee('easytire-input', false);
});
function pricingPayload(array $overrides = []): array
{
    return [...['name' => 'Base', 'scope' => 'global', 'scope_key' => 'ignored', 'source_price_list_code' => 'ndi-file-current', 'source_tax_mode' => 'gross', 'currency' => 'nok', 'markup_percent' => '0', 'fixed_markup_nok' => '0', 'rounding_nok' => '0.01', 'priority' => 1, 'starts_at' => null, 'ends_at' => null], ...$overrides];
}
