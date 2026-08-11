<?php

use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public Norwegian copy follows the customer approved home service wording', function (): void {
    $flatten = static function (array $values) use (&$flatten): array {
        return collect($values)
            ->flatMap(fn (mixed $value): array => is_array($value) ? $flatten($value) : [(string) $value])
            ->all();
    };
    $translations = implode("\n", [
        ...$flatten(require lang_path('nb/pro.php')),
        ...$flatten(require lang_path('nb/storefront.php')),
        ...$flatten(require lang_path('nb/booking.php')),
    ]);

    expect($translations)
        ->toContain('dekkskift hjemme')
        ->not->toContain('Mobilt dekkskift')
        ->not->toContain('Mobil service')
        ->not->toContain('Mobil tjeneste')
        ->not->toContain('É')
        ->not->toContain('é');
});

test('production seed data removes rejected wording without changing the technical slug', function (): void {
    $this->seed(DatabaseSeeder::class);

    $copy = ServicePage::query()
        ->get(['slug', 'title_nb', 'excerpt_nb', 'body_nb', 'seo_title_nb', 'seo_description_nb'])
        ->concat(ContentPage::query()->get(['title_nb', 'excerpt_nb', 'body_nb', 'meta_title_nb', 'meta_description_nb']))
        ->map(fn ($model): string => implode("\n", array_filter($model->getAttributes(), 'is_string')))
        ->implode("\n");

    expect(ServicePage::query()->where('slug', 'mobilt-dekkskift')->exists())->toBeTrue()
        ->and($copy)
        ->toContain('dekkskift hjemme')
        ->not->toContain('Mobilt dekkskift')
        ->not->toContain('mobilt dekkskift')
        ->not->toContain('Mobil service')
        ->not->toContain('mobil service')
        ->not->toContain('Mobil tjeneste')
        ->not->toContain('mobil tjeneste')
        ->not->toContain('É')
        ->not->toContain('é');
});
