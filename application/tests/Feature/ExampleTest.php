<?php

namespace Tests\Feature;

use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_public_home_page_has_the_norwegian_storefront_foundation(): void
    {
        $this->seed(ServicePageSeeder::class);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('EasyTire | Dekk og felg i Tromsø', false)
            ->assertSee(__('pro.home.hero_title'))
            ->assertSee('data-testid="quick-finder"', false)
            ->assertSee(__('pro.home.finder_registration_tab'))
            ->assertSee('Dekkskift hjemme hos deg')
            ->assertSee('lang="nb"', false);
    }
}
