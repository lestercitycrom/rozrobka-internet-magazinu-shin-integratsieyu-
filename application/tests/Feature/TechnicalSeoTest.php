<?php

use App\Modules\Content\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap contains only norwegian indexable routes and published service pages', function (): void {
    ServicePage::create(['slug' => 'published-service', 'sort_order' => 1, 'publication_status' => 'published', 'title_nb' => 'NO', 'title_ru' => 'RU']);
    ServicePage::create(['slug' => 'draft-service', 'sort_order' => 2, 'publication_status' => 'draft', 'title_nb' => 'NO', 'title_ru' => 'RU']);
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('/tjenester/published-service')->assertDontSee('draft-service')->assertDontSee('/ru')->assertDontSee('/admin');
});

test('robots disallows every path outside production', function (): void {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
});

test('missing public pages render a branded norwegian error page', function (): void {
    $this->get('/missing-page')->assertNotFound()->assertSee('EasyTire')->assertSee('Vi finner ikke siden');
});
