<?php

use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('service page stores localized editable fields and enforces a stable unique slug', function (): void {
    $page = servicePage('mobilt-dekkskift');

    expect($page->publication_status)->toBe(ServicePagePublicationStatus::Published)
        ->and($page->request_flow)->toBe(ServiceRequestFlow::Generic)
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
        ->and($russian?->requestFlow)->toBe(ServiceRequestFlow::Generic)
        ->and($russian?->title)->toBe('Сначала')
        ->and($reader->findBySlug('draft'))->toBeNull()
        ->and($reader->findBySlug('missing'))->toBeNull();
});

test('service page seeder provides the six editable service foundations without overwriting edits', function (): void {
    $this->seed(ServicePageSeeder::class);

    expect(ServicePage::query()->count())->toBe(6)
        ->and(ServicePage::query()->orderBy('sort_order')->pluck('slug')->all())->toBe([
            'mobilt-dekkskift', 'dekkverksted', 'tesla-og-elbil', 'bobil-og-varebil', 'dekkhotell', 'dekk-felg-og-hjulpakker',
        ])
        ->and(ServicePage::query()->where('slug', 'mobilt-dekkskift')->firstOrFail()->request_flow)->toBe(ServiceRequestFlow::Seasonal)
        ->and(ServicePage::query()->where('slug', 'dekkverksted')->firstOrFail()->request_flow)->toBe(ServiceRequestFlow::Seasonal)
        ->and(ServicePage::query()->where('slug', 'dekkhotell')->firstOrFail()->request_flow)->toBe(ServiceRequestFlow::Seasonal);

    ServicePage::query()->where('slug', 'dekkhotell')->update(['title_nb' => 'Redigert tittel']);
    $this->seed(ServicePageSeeder::class);

    expect(ServicePage::query()->where('slug', 'dekkhotell')->value('title_nb'))->toBe('Redigert tittel');
});

test('request flow migration backfills the real seasonal service slugs', function (): void {
    Schema::table('service_pages', function (Blueprint $table): void {
        $table->dropIndex(['request_flow']);
        $table->dropColumn('request_flow');
    });

    foreach (['mobilt-dekkskift', 'dekkverksted', 'dekkhotell'] as $index => $slug) {
        DB::table('service_pages')->insert([
            'slug' => $slug,
            'sort_order' => $index + 1,
            'publication_status' => ServicePagePublicationStatus::Published->value,
            'title_nb' => $slug,
            'title_ru' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $migration = require database_path('migrations/2026_08_11_120000_add_request_flow_to_service_pages_table.php');
    $migration->up();

    expect(DB::table('service_pages')->where('slug', 'mobilt-dekkskift')->value('request_flow'))->toBe('seasonal')
        ->and(DB::table('service_pages')->where('slug', 'dekkverksted')->value('request_flow'))->toBe('seasonal')
        ->and(DB::table('service_pages')->where('slug', 'dekkhotell')->value('request_flow'))->toBe('generic');
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
