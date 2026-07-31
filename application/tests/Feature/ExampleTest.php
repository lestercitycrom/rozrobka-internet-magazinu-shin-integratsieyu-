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
            ->assertSee('Dekk og felg som passer hverdagen din')
            ->assertSee('Velg kategori og filtrer deg frem')
            ->assertSee('Mobilt dekkskift')
            ->assertSee('lang="nb"', false);
    }
}
