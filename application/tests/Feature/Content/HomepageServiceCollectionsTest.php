<?php

use App\Models\User;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('homepage service collections render zero one two six and seven cards without a six-card cap', function (): void {
    foreach ([0, 1, 2, 6, 7] as $count) {
        HomepageServiceCard::query()->forceDelete();
        foreach (range(1, $count) as $position) {
            if ($count === 0) {
                break;
            }

            HomepageServiceCard::query()->create(homepageServiceCardPayload([
                'content_key' => 'cardinality-'.$count.'-'.$position,
                'sort_order' => $position,
                'title_nb' => 'Norsk kort '.$position,
                'title_ru' => 'Русская карточка '.$position,
            ]));
        }

        $response = $this->get('/')->assertOk();
        expect(substr_count($response->getContent(), 'data-testid="homepage-service-card"'))->toBe($count);

        if ($count === 0) {
            $response->assertDontSee('data-testid="homepage-additional-services"', false);
        } else {
            $response->assertSee('data-testid="homepage-additional-services"', false);
            expect(substr_count($response->getContent(), 'et-v3-service--feature'))->toBe(min(2, $count));
        }
    }
});

test('legacy fallback is used only before managed collections have ever been initialized', function (): void {
    $service = ServicePage::query()->create([
        'slug' => 'legacy-homepage-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Eldre tjenestekort',
        'title_ru' => 'Старая карточка услуги',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="services-section"', false)
        ->assertSee('Eldre tjenestekort');

    $card = HomepageServiceCard::query()->create(homepageServiceCardPayload([
        'service_page_id' => $service->id,
        'cta_url' => null,
    ]));
    $card->update(['publication_status' => ContentPageStatus::Draft]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-testid="services-section"', false)
        ->assertDontSee('data-testid="homepage-additional-services"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertDontSee('data-testid="services-section"', false)
        ->assertDontSee('Старая карточка услуги');

    $card->delete();
    $this->get('/')
        ->assertOk()
        ->assertDontSee('data-testid="services-section"', false);
    expect(ServicePage::query()->whereKey($service->id)->exists())->toBeTrue();
});

test('one populated managed collection does not revive the legacy block for the empty collection', function (): void {
    ServicePage::query()->create([
        'slug' => 'published-global-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Global tjeneste',
        'title_ru' => 'Глобальная услуга',
    ]);
    HomepageServiceCard::query()->create(homepageServiceCardPayload([
        'collection' => HomepageServiceCollection::Additional,
        'title_nb' => 'Kun tillegg',
        'title_ru' => 'Только дополнительная услуга',
    ]));

    $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="homepage-additional-services"', false)
        ->assertDontSee('data-testid="homepage-wheel-services"', false)
        ->assertDontSee('data-testid="services-section"', false);
});

test('homepage service cards are independently ordered localized and fail closed when unpublished', function (): void {
    HomepageServiceCard::query()->create(homepageServiceCardPayload([
        'collection' => HomepageServiceCollection::Additional,
        'sort_order' => 20,
        'title_nb' => 'Andre norske kort',
        'title_ru' => 'Вторая русская карточка',
    ]));
    $first = HomepageServiceCard::query()->create(homepageServiceCardPayload([
        'content_key' => 'first-card',
        'collection' => HomepageServiceCollection::Additional,
        'sort_order' => 10,
        'title_nb' => 'Første norske kort',
        'title_ru' => 'Первая русская карточка',
    ]));
    HomepageServiceCard::query()->create(homepageServiceCardPayload([
        'content_key' => 'wheel-card',
        'collection' => HomepageServiceCollection::Wheels,
        'sort_order' => 1,
        'title_nb' => 'Hjulkort',
        'title_ru' => 'Карточка колёс',
    ]));

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Første norske kort', 'Andre norske kort', 'Hjulkort'])
        ->assertSee('href="/kontakt"', false);

    config()->set('storefront.ru_preview_enabled', true);
    $this->get('/ru')
        ->assertOk()
        ->assertSeeInOrder(['Первая русская карточка', 'Вторая русская карточка', 'Карточка колёс'])
        ->assertSee('href="/ru/kontakt"', false);

    $first->update(['publication_status' => ContentPageStatus::Draft]);
    $this->get('/')->assertDontSee('Første norske kort');
});

test('administrator can create update reorder unpublish and remove a card without deleting its service', function (): void {
    $admin = User::factory()->owner()->create();
    $service = ServicePage::query()->create([
        'slug' => 'managed-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'title_nb' => 'Administrert tjeneste',
        'title_ru' => 'Управляемая услуга',
    ]);
    $image = homepageServiceCardMedia($admin, true);

    $this->actingAs($admin)->get('/admin/homepage-service-collections/additional')->assertOk();
    $this->actingAs($admin)->get('/admin/homepage-service-collections/wheels')->assertOk();

    $payload = homepageServiceCardPayload([
        'content_key' => null,
        'service_page_id' => $service->id,
        'cta_url' => null,
        'media_asset_id' => $image->id,
    ]);
    unset($payload['collection']);

    $this->actingAs($admin)
        ->post('/admin/homepage-service-collections/additional', $payload)
        ->assertRedirect();

    $card = HomepageServiceCard::query()->firstOrFail();
    expect($card->collection)->toBe(HomepageServiceCollection::Additional)
        ->and($card->service_page_id)->toBe($service->id)
        ->and($card->media_asset_id)->toBe($image->id);

    $card->forceFill(['manifest_hash' => str_repeat('a', 64)])->save();
    $updated = [...$payload, 'sort_order' => 77, 'publication_status' => ContentPageStatus::Draft->value, 'title_nb' => 'Oppdatert'];
    $this->actingAs($admin)
        ->put('/admin/homepage-service-collections/additional/'.$card->id, $updated)
        ->assertRedirect();

    expect($card->fresh()->sort_order)->toBe(77)
        ->and($card->fresh()->publication_status)->toBe(ContentPageStatus::Draft)
        ->and($card->fresh()->manifest_hash)->toBeNull();

    $this->actingAs($admin)
        ->get('/admin/homepage-service-collections/wheels/'.$card->id.'/edit')
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete('/admin/homepage-service-collections/additional/'.$card->id)
        ->assertRedirect();

    expect(HomepageServiceCard::withTrashed()->findOrFail($card->id)->trashed())->toBeTrue()
        ->and(ServicePage::query()->whereKey($service->id)->exists())->toBeTrue();
});

test('published cards require complete bilingual content and a safe resolvable destination', function (): void {
    $admin = User::factory()->owner()->create();
    $payload = homepageServiceCardPayload(['content_key' => null]);
    unset($payload['collection']);

    $this->actingAs($admin)
        ->post('/admin/homepage-service-collections/additional', [...$payload, 'cta_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('cta_url');

    $this->actingAs($admin)
        ->post('/admin/homepage-service-collections/additional', [...$payload, 'description_ru' => ''])
        ->assertSessionHasErrors('description_ru');

    $this->actingAs($admin)
        ->post('/admin/homepage-service-collections/additional', [...$payload, 'cta_url' => ''])
        ->assertSessionHasErrors('cta_url');

    $inactive = homepageServiceCardMedia($admin, false);
    $this->actingAs($admin)
        ->post('/admin/homepage-service-collections/additional', [...$payload, 'media_asset_id' => $inactive->id])
        ->assertSessionHasErrors('media_asset_id');
});

/** @return array<string, mixed> */
function homepageServiceCardPayload(array $overrides = []): array
{
    return [...[
        'content_key' => (string) Str::ulid(),
        'collection' => HomepageServiceCollection::Additional,
        'service_page_id' => null,
        'sort_order' => 10,
        'publication_status' => ContentPageStatus::Published->value,
        'title_nb' => 'Norsk tjeneste',
        'description_nb' => 'Norsk beskrivelse',
        'cta_label_nb' => 'Les mer',
        'title_ru' => 'Русская услуга',
        'description_ru' => 'Русское описание',
        'cta_label_ru' => 'Подробнее',
        'cta_url' => '/kontakt',
        'media_asset_id' => null,
    ], ...$overrides];
}

function homepageServiceCardMedia(User $admin, bool $active): MediaAsset
{
    $suffix = (string) Str::ulid();

    return MediaAsset::query()->create([
        'id' => (string) Str::ulid(),
        'disk' => 'public',
        'path' => 'content/home-card-'.$suffix.'.webp',
        'original_name' => 'home-card-'.$suffix.'.webp',
        'mime' => 'image/webp',
        'byte_size' => 1,
        'width' => 1200,
        'height' => 900,
        'sha256' => hash('sha256', $suffix),
        'uploader_id' => $admin->id,
        'is_active' => $active,
    ]);
}
