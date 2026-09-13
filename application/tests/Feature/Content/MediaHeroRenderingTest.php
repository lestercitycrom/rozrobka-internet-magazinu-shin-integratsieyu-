<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('norwegian published page renders assigned hero once with norwegian alt', function (): void {
    Storage::fake('public');
    $page = heroPage('om-oss');
    $asset = heroAsset('Norsk alt', 'Русский alt');
    assignHero($page, $asset);
    $this->get('/om-oss')->assertOk()->assertSee($asset->path, false)->assertSee('alt="Norsk alt"', false)->assertSee($asset->path, false);
});

test('russian preview uses russian alt then norwegian fallback', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    Storage::fake('public');
    $page = heroPage('kontakt');
    $asset = heroAsset('Fallback', 'Русский alt');
    assignHero($page, $asset);
    $this->get('/ru/kontakt')->assertOk()->assertSee('alt="Русский alt"', false);
    $asset->update(['alt_ru' => null]);
    $this->get('/ru/kontakt')->assertOk()->assertSee('alt="Fallback"', false);
});

test('unassigned published page has no broken hero image and unpublished remains 404', function (): void {
    heroPage('personvern');
    $this->get('/personvern')->assertOk()->assertDontSee('data-testid="page-hero"', false);
    heroPage('kjopsvilkar', ContentPageStatus::Draft);
    $this->get('/kjopsvilkar')->assertNotFound();
});

function heroPage(string $slug, ContentPageStatus $status = ContentPageStatus::Published): ContentPage
{
    return ContentPage::create(['slug' => $slug, 'sort_order' => 1, 'publication_status' => $status, 'title_nb' => 'NO', 'title_ru' => 'RU']);
}
function heroAsset(?string $nb, ?string $ru): MediaAsset
{
    $u = User::factory()->owner()->create();
    $path = 'media/hero.png';
    Storage::disk('public')->put($path, 'x');

    return MediaAsset::create(['disk' => 'public', 'path' => $path.str()->random(4), 'original_name' => 'x.png', 'mime' => 'image/png', 'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64), 'alt_nb' => $nb, 'alt_ru' => $ru, 'uploader_id' => $u->id]);
}
function assignHero(ContentPage $page, MediaAsset $asset): void
{
    MediaAssignment::create(['media_asset_id' => $asset->id, 'assignable_type' => $page::class, 'assignable_id' => $page->id, 'role' => 'hero']);
}
