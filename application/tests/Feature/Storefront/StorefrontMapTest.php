<?php

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use App\Support\GoogleMapsEmbedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only approved HTTPS Google Maps embed URLs are accepted', function (): void {
    expect(GoogleMapsEmbedUrl::sanitize('https://www.google.com/maps/embed?pb=test'))
        ->toBe('https://www.google.com/maps/embed?pb=test')
        ->and(GoogleMapsEmbedUrl::sanitize('https://maps.google.no/maps/embed/v1/place?q=EasyTire'))
        ->toBe('https://maps.google.no/maps/embed/v1/place?q=EasyTire')
        ->and(GoogleMapsEmbedUrl::sanitize('http://www.google.com/maps/embed?pb=test'))->toBeNull()
        ->and(GoogleMapsEmbedUrl::sanitize('https://evil.example/maps/embed?pb=test'))->toBeNull()
        ->and(GoogleMapsEmbedUrl::sanitize('https://www.google.com/search?q=EasyTire'))->toBeNull()
        ->and(GoogleMapsEmbedUrl::sanitize('https://user:pass@www.google.com/maps/embed?pb=test'))->toBeNull();
});

test('contact page enables consent map only for an approved configured URL', function (): void {
    contactPage();

    config(['storefront.map_embed_url' => 'https://www.google.com/maps/embed?pb=easytire']);
    $this->get('/kontakt')
        ->assertOk()
        ->assertSee('data-testid="map-consent"', false)
        ->assertSee('data-map-embed-url="https://www.google.com/maps/embed?pb=easytire"', false)
        ->assertDontSee('<iframe', false);

    config(['storefront.map_embed_url' => 'https://evil.example/maps/embed?pb=easytire']);
    $this->get('/kontakt')
        ->assertOk()
        ->assertSee('data-testid="map-external-only"', false)
        ->assertDontSee('evil.example', false)
        ->assertDontSee('data-testid="map-consent"', false);
});

function contactPage(): ContentPage
{
    return ContentPage::create([
        'slug' => 'kontakt',
        'sort_order' => 1,
        'publication_status' => ContentPageStatus::Published,
        'title_nb' => 'Kontakt',
        'title_ru' => 'Контакты',
    ]);
}
