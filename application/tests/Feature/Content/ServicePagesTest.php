<?php

use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('service page stores localized editable fields and enforces a stable unique slug', function (): void {
    $page = servicePage('mobilt-dekkskift');

    expect($page->publication_status)->toBe(ServicePagePublicationStatus::Published)
        ->and($page->sort_order)->toBeInt()
        ->and($page->title_nb)->toBe('Norsk tittel')
        ->and($page->body_ru)->toBe('Русский текст');

    expect(fn () => servicePage('mobilt-dekkskift'))->toThrow(QueryException::class);
});

test('published service page reader returns only published pages in editorial order and by slug', function (): void {
    servicePage('later', sortOrder: 20, titleNb: 'Senere', titleRu: 'Позже');
    servicePage('first', sortOrder: 10, titleNb: 'Først', titleRu: 'Сначала');
    servicePage('draft', publication: ServicePagePublicationStatus::Draft);
    servicePage('hidden', publication: ServicePagePublicationStatus::Hidden);

    $reader = app(PublishedServicePageReadService::class);
    $list = $reader->list();
    $russian = $reader->findBySlug('first', ServicePageLocale::Russian);

    expect($list->pluck('slug')->all())->toBe(['first', 'later'])
        ->and($list->pluck('title')->all())->toBe(['Først', 'Senere'])
        ->and($russian?->title)->toBe('Сначала')
        ->and($reader->findBySlug('draft'))->toBeNull()
        ->and($reader->findBySlug('missing'))->toBeNull();
});

test('service page seeder provides the six editable service foundations without overwriting edits', function (): void {
    $this->seed(ServicePageSeeder::class);

    expect(ServicePage::query()->count())->toBe(6)
        ->and(ServicePage::query()->orderBy('sort_order')->pluck('slug')->all())->toBe([
            'mobilt-dekkskift', 'dekkverksted', 'tesla-og-elbil', 'bobil-og-varebil', 'dekkhotell', 'dekk-felg-og-hjulpakker',
        ]);

    ServicePage::query()->where('slug', 'dekkhotell')->update(['title_nb' => 'Redigert tittel']);
    $this->seed(ServicePageSeeder::class);

    expect(ServicePage::query()->where('slug', 'dekkhotell')->value('title_nb'))->toBe('Redigert tittel');
});

function servicePage(
    string $slug,
    int $sortOrder = 10,
    ServicePagePublicationStatus $publication = ServicePagePublicationStatus::Published,
    string $titleNb = 'Norsk tittel',
    string $titleRu = 'Русский заголовок',
): ServicePage {
    return ServicePage::create([
        'slug' => $slug, 'sort_order' => $sortOrder, 'publication_status' => $publication,
        'title_nb' => $titleNb, 'excerpt_nb' => 'Norsk utdrag', 'body_nb' => 'Norsk tekst', 'seo_title_nb' => 'Norsk SEO', 'seo_description_nb' => 'Norsk beskrivelse',
        'title_ru' => $titleRu, 'excerpt_ru' => 'Русский анонс', 'body_ru' => 'Русский текст', 'seo_title_ru' => 'Русский SEO', 'seo_description_ru' => 'Русское описание',
    ]);
}
