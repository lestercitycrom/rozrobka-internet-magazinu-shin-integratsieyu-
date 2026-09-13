<?php

use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap contains only norwegian indexable routes and published service pages', function (): void {
    ServicePage::create(['slug' => 'published-service', 'sort_order' => 1, 'publication_status' => 'published', 'title_nb' => 'NO', 'title_ru' => 'RU']);
    ServicePage::create(['slug' => 'draft-service', 'sort_order' => 2, 'publication_status' => 'draft', 'title_nb' => 'NO', 'title_ru' => 'RU']);
    ContentPage::create(['slug' => 'kontakt', 'sort_order' => 1, 'publication_status' => 'published', 'title_nb' => 'Kontakt', 'title_ru' => 'Контакты']);
    ContentPage::create(['slug' => 'personvern', 'sort_order' => 2, 'publication_status' => 'draft', 'title_nb' => 'Personvern', 'title_ru' => 'Конфиденциальность']);
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('/tjenester/published-service')->assertSee('/kontakt')->assertDontSee('draft-service')->assertDontSee('/personvern')->assertDontSee('/ru')->assertDontSee('/admin');
});

test('robots disallows every path outside production', function (): void {
    expect(is_file(public_path('robots.txt')))->toBeFalse('A static robots.txt would bypass the environment-aware Laravel route in nginx.');

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
});

test('dynamic production robots keeps public pages indexable while protecting private areas', function (): void {
    expect(is_file(public_path('robots.txt')))->toBeFalse();
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->get('/robots.txt')->assertOk();
    expect($response->getContent())
        ->toContain("Disallow: /admin\n")
        ->toContain("Disallow: /ru\n")
        ->toContain('Sitemap: ')
        ->not->toContain("Disallow: /\n");
});

test('tracked staging nginx config keeps robots blocked across application rollbacks', function (): void {
    $config = file_get_contents(base_path('../deploy/staging-easytire.nginx.conf'));

    expect($config)
        ->toContain('location = /robots.txt')
        ->toContain('return 200 "User-agent: *\\nDisallow: /\\n";');
});

test('missing public pages render a branded norwegian error page', function (): void {
    $this->get('/missing-page')->assertNotFound()->assertSee('EasyTire')->assertSee('Vi finner ikke siden');
});
