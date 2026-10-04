<?php

use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\StorefrontSettings;
use Database\Seeders\CustomerHomepageCollectionsSeeder;
use Database\Seeders\CustomerServiceMediaLocaleRepairSeeder;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed([ServicePageSeeder::class, SeoServicePageSeeder::class]);
    $this->seed(CustomerHomepageCollectionsSeeder::class);
});

test('customer photography replaces only the previous generic service media and the known Bokmål corruption', function (): void {
    $oilCard = HomepageServiceCard::query()->where('content_key', 'issue-46-additional-oil-change')->firstOrFail();
    $cleaningCard = HomepageServiceCard::query()->where('content_key', 'issue-46-additional-interior-cleaning')->firstOrFail();
    $oilCard->update(['media_asset_id' => MediaAsset::query()->where('path', 'media/homepage/issue-46/workshop.webp')->value('id')]);
    $cleaningCard->update(['media_asset_id' => MediaAsset::query()->where('path', 'media/homepage/issue-46/exterior.webp')->value('id')]);

    StorefrontSettings::query()->create([
        'id' => 1,
        'hero_kicker_nb' => 'Смена колёс в Тромсё',
        'hero_title_nb' => 'Меняем колёса там, где стоит автомобиль.',
        'hero_primary_label_nb' => 'Заказать смену колёс',
        'hero_text_nb' => 'Denne teksten skal beholdes.',
        'store_heading_nb' => 'Шины и диски для вашего автомобиля',
        'store_lead_nb' => 'Найдите подходящие шины, диски и колёсные комплекты. Используйте регистрационный номер или выберите известный размер вручную.',
    ]);

    $this->seed(CustomerServiceMediaLocaleRepairSeeder::class);
    $this->seed(CustomerServiceMediaLocaleRepairSeeder::class);

    $settings = StorefrontSettings::query()->findOrFail(1);
    expect($settings->hero_kicker_nb)->toBe('Dekkskift i Tromsø')
        ->and($settings->hero_title_nb)->toBe('Vi skifter hjul der bilen står.')
        ->and($settings->hero_primary_label_nb)->toBe('Bestill dekkskift')
        ->and($settings->hero_text_nb)->toBe('Denne teksten skal beholdes.')
        ->and($settings->store_heading_nb)->toBe('Dekk og felger til bilen din')
        ->and($settings->store_lead_nb)->toStartWith('Finn riktige dekk, felger og komplette hjul');

    $oilPath = $oilCard->fresh()->mediaAsset()->value('path');
    $cleaningPath = $cleaningCard->fresh()->mediaAsset()->value('path');
    expect($oilPath)->toBe('media/homepage/issue-46/customer-oil-change.webp')
        ->and($cleaningPath)->toBe('media/homepage/issue-46/customer-interior-cleaning.webp')
        ->and(MediaAsset::query()->whereIn('path', [$oilPath, $cleaningPath])->count())->toBe(2);

    Storage::disk('public')->assertExists($oilPath);
    Storage::disk('public')->assertExists($cleaningPath);

    $this->get('/')
        ->assertOk()
        ->assertSee('Vi skifter hjul der bilen står.')
        ->assertSee('Bestill dekkskift')
        ->assertSee('/storage/'.$oilPath, false)
        ->assertSee('/storage/'.$cleaningPath, false)
        ->assertDontSee('Меняем колёса');

    $this->get('/dekk-og-felger')
        ->assertOk()
        ->assertSee('Dekk og felger til bilen din')
        ->assertDontSee('Шины и диски для вашего автомобиля');
});

test('repair preserves later operator copy and an operator-selected image', function (): void {
    $operatorAsset = MediaAsset::query()->create([
        'id' => (string) Str::ulid(),
        'disk' => 'public',
        'path' => 'media/operator/oil.webp',
        'original_name' => 'oil.webp',
        'mime' => 'image/webp',
        'byte_size' => 1,
        'width' => 1,
        'height' => 1,
        'sha256' => hash('sha256', 'operator-oil'),
        'alt_nb' => 'Operatørbilde',
        'alt_ru' => 'Изображение оператора',
        'uploader_id' => null,
        'is_active' => true,
    ]);
    $oilCard = HomepageServiceCard::query()->where('content_key', 'issue-46-additional-oil-change')->firstOrFail();
    $oilCard->update(['media_asset_id' => $operatorAsset->id]);
    StorefrontSettings::query()->create([
        'id' => 1,
        'hero_title_nb' => 'Operatørens egen overskrift',
    ]);

    $this->seed(CustomerServiceMediaLocaleRepairSeeder::class);

    expect(StorefrontSettings::query()->findOrFail(1)->hero_title_nb)->toBe('Operatørens egen overskrift')
        ->and($oilCard->fresh()->media_asset_id)->toBe($operatorAsset->id);
});
