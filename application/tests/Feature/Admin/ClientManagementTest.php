<?php

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('operations can save find and edit a client without creating a request', function (): void {
    $staff = User::factory()->operations()->create();

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        'name' => 'Kari Nordmann',
        'phone' => '99 88 77 66',
        'email' => 'KARI@example.test',
        'address' => 'Ringvegen 200',
        'district' => 'Tromsdalen',
        'note' => 'Foretrekker SMS.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $client = Client::query()->sole();
    expect($client->phone_normalized)->toBe('4799887766')
        ->and($client->email)->toBe('kari@example.test')
        ->and($client->created_by_user_id)->toBe($staff->id)
        ->and(ServiceRequest::query()->count())->toBe(0);

    $this->actingAs($staff)->get(route('admin.clients.index', ['q' => '99 88 77 66']))
        ->assertOk()->assertSee('Kari Nordmann');

    $this->actingAs($staff)->put(route('admin.clients.update', $client), [
        'name' => 'Kari Hansen',
        'phone' => '+47 911 11 111',
        'email' => '',
        'district' => 'Sentrum',
    ])->assertRedirect(route('admin.clients.show', $client));

    expect($client->fresh()->name)->toBe('Kari Hansen')
        ->and($client->fresh()->phone_normalized)->toBe('4791111111')
        ->and($client->fresh()->email)->toBeNull();
});

test('client validation identifies the exact invalid control accessibly', function (): void {
    $staff = User::factory()->operations()->create(['locale' => 'ru']);

    $response = $this->actingAs($staff)
        ->from(route('admin.clients.create'))
        ->followingRedirects()
        ->post(route('admin.clients.store'), ['name' => 'Иван', 'phone' => 'bad'])
        ->assertOk();

    $response
        ->assertOk()
        ->assertSee('id="client-phone"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="client-phone-error"', false)
        ->assertSee('id="client-phone-error"', false);
});

test('client request links are explicit and preserve the request contact snapshot', function (): void {
    $service = issue59Service();
    $staff = User::factory()->operations()->create();
    $client = Client::query()->create([
        'name' => 'Original Client', 'phone' => '90000000', 'phone_normalized' => '4790000000',
        'email' => 'client@example.test', 'district' => 'Kroken', 'created_by_user_id' => $staff->id,
    ]);

    $this->actingAs($staff)->get(route('admin.service-requests.create', ['client' => $client->id]))
        ->assertOk()
        ->assertSee('value="Original Client"', false)
        ->assertSee('value="'.$client->id.'" selected', false);

    $this->actingAs($staff)->post(route('admin.service-requests.store'), issue59RequestPayload($service, [
        'client_id' => $client->id,
        'name' => 'Request Snapshot',
        'phone' => '92222222',
        'district' => 'Kroken',
    ]))->assertRedirect();

    $request = ServiceRequest::query()->sole();
    expect($request->client_id)->toBe($client->id)
        ->and($request->name)->toBe('Request Snapshot')
        ->and($request->phone)->toBe('92222222');

    $this->actingAs($staff)->put(route('admin.clients.update', $client), [
        'name' => 'Changed Client', 'phone' => '93333333', 'email' => 'changed@example.test',
    ])->assertRedirect();

    expect($request->fresh()->name)->toBe('Request Snapshot')
        ->and($request->fresh()->phone)->toBe('92222222')
        ->and($request->fresh()->email)->toBe('client@example.test');

    $this->actingAs($staff)->get(route('admin.clients.show', $client))
        ->assertOk()->assertSee($request->reference)->assertSee(route('admin.service-requests.show', $request), false);
});

test('an existing unlinked request can create and link a client without rewriting its snapshot', function (): void {
    $service = issue59Service();
    $staff = User::factory()->operations()->create();
    $request = ServiceRequest::query()->create([
        'reference' => 'SR-LINK-59', 'locale' => 'nb', 'source' => ServiceRequestSource::Public,
        'service_slug' => $service->slug, 'fulfilment' => 'mobile', 'name' => 'Public Snapshot',
        'phone' => '94444444', 'phone_normalized' => '4794444444', 'email' => 'public@example.test',
        'address' => 'Old address', 'district' => 'Old district', 'status' => ServiceRequestStatus::New,
        'consent_at' => now(),
    ]);

    $this->actingAs($staff)->get(route('admin.clients.create', ['service_request' => $request->id]))
        ->assertOk()->assertSee('SR-LINK-59')->assertSee('value="Public Snapshot"', false);

    $this->actingAs($staff)->post(route('admin.clients.store'), [
        'name' => 'Client Record', 'phone' => '95555555', 'email' => 'record@example.test',
        'address' => 'New address', 'district' => 'New district', 'link_request_id' => $request->id,
        'expected_version' => $request->fresh()->operation_version, 'command_id' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $client = Client::query()->sole();
    $request->refresh();
    expect($request->client_id)->toBe($client->id)
        ->and($request->name)->toBe('Public Snapshot')
        ->and($request->phone)->toBe('94444444')
        ->and($request->address)->toBe('Old address');
});

test('client PII and forged request links are denied outside owner and operations', function (): void {
    $service = issue59Service();
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();
    $client = Client::query()->create([
        'name' => 'Protected Client', 'phone' => '96666666', 'phone_normalized' => '4796666666',
        'created_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)->get(route('admin.clients.show', $client))->assertOk();
    $this->actingAs($operations)->get(route('admin.clients.show', $client))->assertOk();
    $this->actingAs($editor)->get(route('admin.clients.index'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.clients.update', $client), ['name' => 'Leak', 'phone' => '97777777'])->assertForbidden();
    $this->actingAs($customer)->get(route('admin.clients.show', $client))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.clients.show', $client))->assertRedirect(route('admin.login'));

    $this->actingAs($operations)->post(route('admin.service-requests.store'), issue59RequestPayload($service, [
        'client_id' => 999999,
    ]))->assertSessionHasErrors('client_id');
    expect(ServiceRequest::query()->count())->toBe(0);
});

function issue59Service(): ServicePage
{
    return ServicePage::query()->create([
        'slug' => 'issue-59-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Generic,
        'title_nb' => 'Kundetjeneste',
        'title_ru' => 'Услуга клиента',
    ]);
}

/** @return array<string, mixed> */
function issue59RequestPayload(ServicePage $service, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(),
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'name' => 'Request Client',
        'email' => 'client@example.test',
        'phone' => '90000000',
    ], $override);
}
