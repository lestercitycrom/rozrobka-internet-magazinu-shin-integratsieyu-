<?php

use App\Models\User;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\Models\StorefrontSettings;
use App\Modules\Content\ReadModel\StorefrontSettingsReadService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('empty storefront settings preserve the existing public defaults', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('pro.home.hero_title'))
        ->assertSee(config('storefront.phone_display'));

    $this->get('/dekk-og-felger')
        ->assertOk()
        ->assertSee(__('seo_store.heading'));
});

test('administrator can edit typed localized storefront settings and public pages use them', function (): void {
    $this->seed(DatabaseSeeder::class);
    $admin = User::factory()->operations()->create();
    $asset = MediaAsset::query()->create([
        'id' => (string) Str::ulid(), 'disk' => 'public', 'path' => 'content/custom-hero.webp', 'original_name' => 'custom-hero.webp',
        'mime' => 'image/webp', 'byte_size' => 1, 'width' => 1600, 'height' => 900, 'sha256' => hash('sha256', 'custom-hero'),
        'alt_nb' => 'Tilpasset heltebilde', 'alt_ru' => 'Настроенное главное изображение', 'uploader_id' => $admin->id, 'is_active' => true,
    ]);

    $payload = storefrontSettingsPayload($asset);
    $this->actingAs($admin)->get('/admin/storefront-settings')->assertOk()->assertSee('Butikkfront');
    $this->actingAs($admin)->put('/admin/storefront-settings', $payload)->assertRedirect('/admin/storefront-settings');

    expect(StorefrontSettings::query()->findOrFail(1)->hero_media_asset_id)->toBe($asset->id);

    $this->get('/')
        ->assertOk()
        ->assertSee('Hjulhjelp i Troms')
        ->assertSee('For bilparken')
        ->assertDontSee('Vårt lokale hjulteam')
        ->assertSee('Dekkhotellet er klart')
        ->assertSee('Tilpasset heltebilde')
        ->assertSee('custom-hero.webp')
        ->assertSee('+47 900 00 000')
        ->assertSee('maps.example.test/easytire');
    $this->get('/dekk-og-felger')->assertOk()->assertSee('Finn hjul som passer')->assertSee('Lokalt i Troms')->assertSee('+47 900 00 000');
    ServicePage::query()->create(['slug' => 'redaksjonell-test', 'sort_order' => 99, 'publication_status' => 'published', 'title_nb' => 'Redaksjonell test', 'title_ru' => 'Редакционный тест']);
    $this->get('/tjenester/redaksjonell-test')
        ->assertOk()
        ->assertSee('+47 900 00 000')
        ->assertSee('href="tel:+4790000000"', false)
        ->assertDontSee('href="tel:+4793958270"', false)
        ->assertSee('kontakt@example.test');
    $this->get('/tjenester')
        ->assertOk()
        ->assertSee('href="tel:+4790000000"', false)
        ->assertDontSee('href="tel:+4793958270"', false);
    $this->get('/dekkreparasjon-tromso')
        ->assertOk()
        ->assertSee('data-testid="service-hero-call"', false)
        ->assertSee('href="tel:+4790000000"', false)
        ->assertDontSee('href="tel:+4793958270"', false);
    $this->get('/kontakt')
        ->assertOk()
        ->assertSee('+47 900 00 000')
        ->assertSee('kontakt@example.test')
        ->assertSee('data-testid="map-iframe"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')->assertOk()->assertSee('Хранение готово')->assertDontSee('Наша команда');
    $this->get('/ru')->assertOk()->assertSee('Шины и диски в Тромсё')->assertSee('Настроенное главное изображение');
    $this->get('/ru/dekk-og-felger')->assertOk()->assertSee('Подберите подходящие колёса')->assertSee('Проверенные варианты');
});

test('a subsequently deactivated hero media asset falls back safely', function (): void {
    $admin = User::factory()->operations()->create();
    $asset = MediaAsset::query()->create([
        'id' => (string) Str::ulid(), 'disk' => 'public', 'path' => 'content/deactivated-hero.webp', 'original_name' => 'deactivated-hero.webp',
        'mime' => 'image/webp', 'byte_size' => 1, 'width' => 1600, 'height' => 900, 'sha256' => hash('sha256', 'deactivated-hero'),
        'uploader_id' => $admin->id, 'is_active' => true,
    ]);
    StorefrontSettings::query()->create(['id' => 1, 'hero_media_asset_id' => $asset->id]);
    $asset->update(['is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('deactivated-hero.webp')
        ->assertSee('/brand/hero-service-mobile.webp', false)
        ->assertSee('/brand/hero-service-desktop.webp', false);
});

test('storefront settings reject unsafe CTA links and inactive media', function (): void {
    $admin = User::factory()->operations()->create();
    $inactiveAsset = MediaAsset::query()->create([
        'id' => (string) Str::ulid(), 'disk' => 'public', 'path' => 'content/inactive.webp', 'original_name' => 'inactive.webp',
        'mime' => 'image/webp', 'byte_size' => 1, 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', 'inactive'),
        'uploader_id' => $admin->id, 'is_active' => false,
    ]);

    $this->actingAs($admin)->put('/admin/storefront-settings', [
        'hero_primary_url' => 'javascript:alert(1)', 'hero_media_asset_id' => $inactiveAsset->id, 'map_embed_url' => 'https://example.test/not-a-map',
    ])->assertSessionHasErrors(['hero_primary_url', 'hero_media_asset_id', 'map_embed_url']);
});

test('administrator can save only safe public social links and the read model has a Facebook fallback', function (): void {
    $admin = User::factory()->operations()->create();

    $this->actingAs($admin)
        ->put('/admin/storefront-settings', [
            'instagram_url' => 'https://www.instagram.com/easytire/',
            'snapchat_url' => 'https://www.snapchat.com/add/easytire',
            'facebook_url' => 'https://www.facebook.com/easytire/',
        ])
        ->assertRedirect('/admin/storefront-settings');

    expect(StorefrontSettings::query()->findOrFail(1)->only(['instagram_url', 'snapchat_url', 'facebook_url']))->toBe([
        'instagram_url' => 'https://www.instagram.com/easytire/',
        'snapchat_url' => 'https://www.snapchat.com/add/easytire',
        'facebook_url' => 'https://www.facebook.com/easytire/',
    ]);
    expect(app(StorefrontSettingsReadService::class)->socialLinks())->toBe([
        'instagram_url' => 'https://www.instagram.com/easytire/',
        'snapchat_url' => 'https://www.snapchat.com/add/easytire',
        'facebook_url' => 'https://www.facebook.com/easytire/',
    ]);

    $this->actingAs($admin)
        ->put('/admin/storefront-settings', ['instagram_url' => 'javascript:alert(1)', 'snapchat_url' => 'https://user:pass@example.test/'])
        ->assertSessionHasErrors(['instagram_url', 'snapchat_url']);

    StorefrontSettings::query()->updateOrCreate(['id' => 1], ['facebook_url' => null]);
    expect(app(StorefrontSettingsReadService::class)->socialLinks()['facebook_url'])
        ->toBe(config('storefront.facebook_url'));
});

function storefrontSettingsPayload(MediaAsset $asset): array
{
    return [
        'hero_kicker_nb' => 'Troms', 'hero_title_nb' => 'Hjulhjelp i Troms', 'hero_text_nb' => 'Trygg hjelp nær deg.', 'hero_primary_label_nb' => 'Bestill nå', 'hero_secondary_label_nb' => 'Se utvalget',
        'hero_kicker_ru' => 'Тромсё', 'hero_title_ru' => 'Шины и диски в Тромсё', 'hero_text_ru' => 'Понятная помощь рядом.', 'hero_primary_label_ru' => 'Записаться', 'hero_secondary_label_ru' => 'Смотреть ассортимент',
        'hero_primary_url' => '/tjenester', 'hero_secondary_url' => 'https://example.test/catalog', 'hero_media_asset_id' => $asset->id,
        'phone_display' => '+47 900 00 000', 'phone_href' => '+4790000000', 'email' => 'kontakt@example.test', 'business_address' => 'Ringvegen 200, 9018 Tromsø', 'map_pin_address' => 'Ringvegen 202, 9018 Tromsø',
        'map_url' => 'https://maps.example.test/easytire', 'map_embed_url' => 'https://www.google.com/maps?q=EasyTire&output=embed',
        'store_heading_nb' => 'Finn hjul som passer', 'store_lead_nb' => 'Lokalt i Troms og med en enkel prosess.',
        'store_heading_ru' => 'Подберите подходящие колёса', 'store_lead_ru' => 'Проверенные варианты и понятный процесс.',
        'store_benefit_1_title_nb' => 'Lokalt i Troms', 'store_benefit_1_text_nb' => 'Råd fra verkstedet.',
        'store_benefit_2_title_nb' => 'Riktig utvalg', 'store_benefit_2_text_nb' => 'Vi dobbeltsjekker.',
        'store_benefit_3_title_nb' => 'Klart neste steg', 'store_benefit_3_text_nb' => 'Du får svar.',
        'store_benefit_1_title_ru' => 'Проверенные варианты', 'store_benefit_1_text_ru' => 'Советуем по делу.',
        'store_benefit_2_title_ru' => 'Подходящий выбор', 'store_benefit_2_text_ru' => 'Перепроверяем.',
        'store_benefit_3_title_ru' => 'Понятный следующий шаг', 'store_benefit_3_text_ru' => 'Ответим.',
        'home_store_media_asset_id' => $asset->id, 'story_media_asset_id' => $asset->id, 'hotel_media_asset_id' => $asset->id, 'mobile_media_asset_id' => $asset->id,
        'chooser_kicker_nb' => 'Velg enkelt', 'chooser_title_nb' => 'Hvor passer det?', 'trust_1_nb' => 'Enkel bestilling',
        'process_kicker_nb' => 'Slik går det', 'process_title_nb' => 'Fire tydelige steg', 'process_lead_nb' => 'Vi holder det enkelt.', 'process_1_title_nb' => 'Velg', 'process_1_text_nb' => 'Velg hva som passer.',
        'story_kicker_nb' => 'Lokalt team', 'story_title_nb' => 'Vårt lokale hjulteam', 'story_text_nb' => 'Ekte folk og ekte hjelp.', 'story_quote_nb' => 'Kort vei til hjelp.',
        'business_kicker_nb' => 'Bedrift', 'business_title_nb' => 'For bilparken', 'business_text_nb' => 'Vi hjelper bedrifter.', 'business_proof_nb' => 'Dokumentert erfaring.',
        'logistics_kicker_nb' => 'Oppbevaring og hjelp', 'logistics_title_nb' => 'Tilpasset hverdagen', 'hotel_kicker_nb' => 'Dekkhotell', 'hotel_title_nb' => 'Dekkhotellet er klart', 'hotel_text_nb' => 'Trygg oppbevaring.', 'hotel_cta_nb' => 'Se lagring', 'mobile_kicker_nb' => 'Hos deg', 'mobile_title_nb' => 'Vi kommer', 'mobile_text_nb' => 'Vi møter deg.', 'mobile_cta_nb' => 'Bestill hjemme',
        'contact_kicker_nb' => 'Kontakt', 'contact_title_nb' => 'Snakk med oss', 'contact_text_nb' => 'Vi svarer raskt.',
        'chooser_kicker_ru' => 'Простой выбор', 'chooser_title_ru' => 'Где удобнее?', 'trust_1_ru' => 'Простая запись', 'story_title_ru' => 'Наша команда', 'hotel_title_ru' => 'Хранение готово', 'contact_title_ru' => 'Свяжитесь с нами',
    ];
}
