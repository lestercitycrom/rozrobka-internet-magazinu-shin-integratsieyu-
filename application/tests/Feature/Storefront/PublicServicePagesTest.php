<?php

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('published Norwegian service pages are public and drafts are not', function (): void {
    servicePreviewPage('mobile', ServicePagePublicationStatus::Published);
    servicePreviewPage('draft', ServicePagePublicationStatus::Draft);
    $this->get('/tjenester')->assertOk()->assertSee('Mobil tjeneste');
    $this->get('/tjenester/mobile')
        ->assertOk()
        ->assertSee('Norsk innhold')
        ->assertSee('href="'.route('storefront.requests.create', 'mobile').'"', false);
    $this->get('/tjenester/draft')->assertNotFound();
});

test('Russian service preview keeps the selected service in its request CTA', function (): void {
    servicePreviewPage('mobile', ServicePagePublicationStatus::Published);
    config()->set('storefront.ru_preview_enabled', true);

    $this->get('/ru/tjenester/mobile')
        ->assertOk()
        ->assertSee('href="'.route('storefront.preview.requests.create', 'mobile').'"', false);
});

test('homepage hides the services block when no services are published in NB and RU preview', function (): void {
    $this->get('/')->assertOk()->assertDontSee('data-testid="services-section"', false);
    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')->assertOk()->assertDontSee('data-testid="services-section"', false);
});

test('homepage renders the services block when a service is published', function (): void {
    servicePreviewPage('mobile', ServicePagePublicationStatus::Published);
    $this->get('/')->assertOk()->assertSee('data-testid="services-section"', false)->assertSee('Mobil tjeneste');
});

test('Russian service preview is gated and noindexed with Norwegian canonical', function (): void {
    servicePreviewPage('mobile', ServicePagePublicationStatus::Published);
    config()->set('storefront.ru_preview_enabled', false);
    $this->get('/ru/tjenester')->assertNotFound();
    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru/tjenester/mobile')->assertOk()->assertSee('Русское содержание')->assertSee('noindex,nofollow', false)->assertSee('/tjenester/mobile', false)->assertDontSee('Norsk innhold');
});

function servicePreviewPage(string $slug, ServicePagePublicationStatus $status): void
{
    ServicePage::create(['slug' => $slug, 'sort_order' => 1, 'publication_status' => $status, 'title_nb' => 'Mobil tjeneste', 'excerpt_nb' => 'Norsk utdrag', 'body_nb' => 'Norsk innhold', 'seo_title_nb' => null, 'seo_description_nb' => null, 'title_ru' => 'Мобильная услуга', 'excerpt_ru' => 'Русский анонс', 'body_ru' => 'Русское содержание', 'seo_title_ru' => null, 'seo_description_ru' => null]);
}
