<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FrontendV2ContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_error_pages_render_when_service_pages_table_is_unavailable(): void
    {
        Schema::dropIfExists('service_pages');

        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('EasyTire');
    }

    public function test_state_changing_method_on_home_remains_method_not_allowed_without_service_pages(): void
    {
        Schema::dropIfExists('service_pages');

        $this->post('/')
            ->assertStatus(405)
            ->assertSee('EasyTire');
    }

    public function test_storefront_layout_keeps_published_service_navigation_and_safe_empty_fallback_contract(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/storefront.blade.php'));

        self::assertIsString($layout);
        self::assertStringContainsString("Schema::hasTable('service_pages')", $layout);
        self::assertStringContainsString('PublishedServicePageReadService::class', $layout);
        self::assertStringContainsString('$headerServices->firstWhere', $layout);
        self::assertStringContainsString('$serviceDestination', $layout);
        self::assertStringContainsString("route(\$routePrefix.'services.index')", $layout);
        self::assertStringContainsString('catch (\\Throwable)', $layout);
    }

    public function test_request_view_uses_stable_flow_fields_and_narrow_seasonal_slug_fallback(): void
    {
        $view = file_get_contents(resource_path('views/storefront/requests/create.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString("data_get(\$service, 'requestFlow')", $view);
        self::assertStringContainsString("'mobilt-dekkskift'", $view);
        self::assertStringContainsString("'dekkverksted'", $this->seasonalSlugBlock($view));
        self::assertStringNotContainsString("'dekkhotell'", $this->seasonalSlugBlock($view));
        self::assertStringNotContainsString('str_contains($serviceTitle', $view);
        self::assertStringNotContainsString('mb_stripos((string) $service->title', $view);
        self::assertStringContainsString('data-testid="seasonal-service-request"', $view);
        self::assertStringContainsString('data-testid="generic-service-request"', $view);
        self::assertStringContainsString('id="booking-fulfilment"', $view);
    }

    public function test_homepage_uses_persisted_flow_before_real_slug_fallback(): void
    {
        $view = file_get_contents(resource_path('views/welcome.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString("data_get(\$service, 'requestFlow')", $view);
        self::assertStringContainsString('instanceof \\BackedEnum', $view);
        self::assertStringContainsString("'dekkverksted'", $this->seasonalSlugBlock($view));
        self::assertStringNotContainsString("'dekkhotell'", $this->seasonalSlugBlock($view));
    }

    public function test_current_seasonal_contract_exposes_the_customer_approved_multi_car_and_storage_fields(): void
    {
        $view = file_get_contents(resource_path('views/storefront/requests/create.blade.php'));

        self::assertIsString($view);
        foreach (['car_count', 'vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan', 'vehicle_2', 'storage_plan_2', 'fulfilment', 'address', 'preferred_date', 'preferred_window'] as $name) {
            self::assertStringContainsString('name="'.$name.'"', $view);
        }
        self::assertStringContainsString("['none', 'new', 'renew']", $view);
        self::assertStringContainsString('data-seasonal-quote', $view);
        self::assertStringNotContainsString('name="dekkhotell"', $view);
    }

    private function seasonalSlugBlock(string $view): string
    {
        $start = strpos($view, '$seasonalSlugs = [');
        $end = $start === false ? false : strpos($view, '];', $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        return substr($view, $start, $end - $start + 2);
    }
}
