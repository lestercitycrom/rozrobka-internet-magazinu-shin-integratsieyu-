<?php

use App\Models\User;
use App\Modules\Pricing\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins create pricing rules with exact input conversions and scope rules', function (): void {
    $admin = User::factory()->operations()->create();
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['markup_percent' => '12.34', 'fixed_markup_nok' => '5.50', 'rounding_nok' => '1.00']))->assertRedirect();
    $rule = PricingRule::firstOrFail();
    expect($rule->markup_basis_points)->toBe(1234)->and($rule->fixed_markup_minor)->toBe(550)->and($rule->rounding_increment_minor)->toBe(100)->and($rule->scope_key)->toBeNull();
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['scope' => 'brand', 'scope_key' => '']))->assertSessionHasErrors('scope_key');
});
test('active overlapping rules are rejected but inactive and nonoverlapping rules are allowed', function (): void {
    $admin = User::factory()->operations()->create();
    $payload = pricingPayload(['is_active' => '1', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
    $this->actingAs($admin)->post('/admin/pricing-rules', $payload)->assertRedirect();
    $this->actingAs($admin)->post('/admin/pricing-rules', $payload)->assertSessionHasErrors('priority');
    $this->actingAs($admin)->post('/admin/pricing-rules', pricingPayload(['is_active' => '1', 'starts_at' => '2027-01-01', 'ends_at' => '2027-12-31']))->assertRedirect();
});
test('guests and customers cannot manage pricing rules', function (): void {
    $this->get('/admin/pricing-rules')->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->customer()->create())->get('/admin/pricing-rules')->assertForbidden();
});
function pricingPayload(array $overrides = []): array
{
    return [...['name' => 'Base', 'scope' => 'global', 'scope_key' => 'ignored', 'source_price_list_code' => 'ndi-file-current', 'currency' => 'nok', 'markup_percent' => '0', 'fixed_markup_nok' => '0', 'rounding_nok' => '0.01', 'priority' => 1, 'starts_at' => null, 'ends_at' => null], ...$overrides];
}
