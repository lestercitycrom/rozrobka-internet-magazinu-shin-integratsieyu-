<?php

use App\Models\User;
use App\Modules\Commerce\Models\ServiceAddon;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;

uses(RefreshDatabase::class);

dataset('admin locales', [
    'Norwegian Bokmål' => ['nb', 'Innholdssider', '‹ Forrige'],
    'Russian' => ['ru', 'Информационные страницы', '‹ Назад'],
]);

test('content, payment and pagination translations are complete', function (string $locale, string $heading, string $previous): void {
    $admin = User::factory()->owner()->create(['locale' => $locale]);
    $now = now();

    foreach (range(1, 21) as $index) {
        ContentPage::create([
            'slug' => 'coverage-'.$index,
            'sort_order' => $index,
            'publication_status' => ContentPageStatus::Draft,
            'title_nb' => 'Norsk '.$index,
            'title_ru' => 'Русский '.$index,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $addon = ServiceAddon::query()->firstOrFail();
    $responses = [
        $this->actingAs($admin)->get('/admin/content-pages'),
        $this->actingAs($admin)->get('/admin/content-pages/create'),
        $this->actingAs($admin)->get('/admin/service-addons'),
        $this->actingAs($admin)->get('/admin/service-addons/'.$addon->id.'/edit'),
    ];

    foreach ($responses as $response) {
        $response->assertOk()
            ->assertDontSee('admin.content.', false)
            ->assertDontSee('payment_statuses.', false)
            ->assertDontSee('pagination.', false);
    }

    $responses[0]->assertSee($heading)->assertSee($previous);

    foreach ([
        'admin.content.title', 'admin.content.heading', 'admin.content.intro', 'admin.content.new',
        'admin.content.edit', 'admin.content.slug', 'admin.content.order', 'admin.content.status',
        'admin.content.title_nb', 'admin.content.title_ru', 'admin.content.field_title', 'admin.content.excerpt',
        'admin.content.body', 'admin.content.meta_title', 'admin.content.meta_description',
        'admin.content.locale_nb', 'admin.content.locale_ru', 'admin.content.save', 'admin.content.delete',
        'admin.content.saved', 'admin.content.deleted', 'admin.content.empty', 'admin.content.actions',
        'admin.content.table_label', 'payment_statuses.capturing', 'payment_statuses.voiding',
        'payment_statuses.voided', 'pagination.previous', 'pagination.next',
    ] as $key) {
        expect(Lang::has($key, $locale, false))->toBeTrue("Missing [{$locale}] translation: {$key}")
            ->and(__($key, [], $locale))->not->toBe($key);
    }
})->with('admin locales');
