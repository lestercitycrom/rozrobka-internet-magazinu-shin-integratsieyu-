<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_public_home_page_has_the_norwegian_storefront_foundation(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('EasyTire | Dekk og felg i Tromsø', false)
            ->assertSee('Dekk og felg – på din måte')
            ->assertSee('Søk etter dekk og felg')
            ->assertSee('Mobilt dekkskift')
            ->assertSee('lang="nb"', false);
    }
}
