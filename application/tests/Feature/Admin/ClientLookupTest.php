<?php

use App\Models\User;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client lookup is restricted to owner and operations and returns minimal private JSON', function (): void {
    $owner = User::factory()->owner()->create();
    $operations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $customer = User::factory()->customer()->create();
    $inactive = User::factory()->operations()->inactive()->create();
    $client = issue66LookupClient([
        'name' => 'Minimal Lookup Client',
        'phone' => '99 11 22 33',
        'phone_normalized' => '4799112233',
        'email' => 'private-lookup@example.test',
        'address' => 'Privatevegen 66',
        'district' => 'Tromsdalen',
        'note' => 'This note must never enter lookup JSON.',
    ]);

    $this->get(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertRedirect(route('admin.login'));
    $this->actingAs($editor)->getJson(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertForbidden();
    $this->actingAs($customer)->getJson(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertForbidden();
    $this->actingAs($inactive)->getJson(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertForbidden();

    $this->actingAs($owner)->getJson(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertOk();

    $response = $this->actingAs($operations)
        ->getJson(route('admin.clients.lookup', ['q' => 'Minimal Lookup']))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json())->toBe([
        'items' => [[
            'id' => $client->id,
            'label' => 'Minimal Lookup Client · 99 11 22 33',
        ]],
        'pagination' => [
            'page' => 1,
            'has_more' => false,
        ],
    ]);
});

test('client lookup uses stable server pagination and can find clients beyond the former 250 option limit', function (): void {
    $operations = User::factory()->operations()->create();
    $now = now();

    foreach (array_chunk(range(1, 1001), 50) as $chunk) {
        Client::query()->insert(array_map(static fn (int $index): array => [
            'name' => sprintf('Bulk Directory %04d', $index),
            'phone' => sprintf('91%06d', $index),
            'phone_normalized' => sprintf('4791%06d', $index),
            'email' => sprintf('bulk-%03d@example.test', $index),
            'address' => null,
            'district' => null,
            'note' => null,
            'created_by_user_id' => $operations->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    $first = $this->actingAs($operations)
        ->getJson(route('admin.clients.lookup', ['q' => 'Bulk Directory', 'page' => 1]))
        ->assertOk();
    $second = $this->actingAs($operations)
        ->getJson(route('admin.clients.lookup', ['q' => 'Bulk Directory', 'page' => 2]))
        ->assertOk();

    $firstItems = $first->json('items');
    $secondItems = $second->json('items');
    expect($firstItems)->not->toBeEmpty()
        ->and($secondItems)->not->toBeEmpty()
        ->and($first->json('pagination.page'))->toBe(1)
        ->and($first->json('pagination.has_more'))->toBeTrue()
        ->and($second->json('pagination.page'))->toBe(2)
        ->and(array_intersect(
            array_column($firstItems, 'id'),
            array_column($secondItems, 'id'),
        ))->toBe([])
        ->and(array_column($firstItems, 'label'))->toBe(
            collect(array_column($firstItems, 'label'))->sort()->values()->all(),
        )
        ->and(array_column($secondItems, 'label'))->toBe(
            collect(array_column($secondItems, 'label'))->sort()->values()->all(),
        );

    $lastClient = Client::query()->where('name', 'Bulk Directory 1001')->sole();
    $this->actingAs($operations)
        ->getJson(route('admin.clients.lookup', ['q' => 'Bulk Directory 1001']))
        ->assertOk()
        ->assertJsonPath('items.0.id', $lastClient->id)
        ->assertJsonCount(1, 'items');
});

test('client lookup searches client fields and registration snapshots without treating storage numbers or unlinked requests as ownership', function (): void {
    $operations = User::factory()->operations()->create();
    $nameClient = issue66LookupClient(['name' => 'Needle Name Client']);
    $emailClient = issue66LookupClient([
        'name' => 'Email Match Client',
        'email' => 'needle-email-66@example.test',
    ]);
    $phoneClient = issue66LookupClient([
        'name' => 'Phone Match Client',
        'phone' => '99 88 77 66',
        'phone_normalized' => '4799887766',
    ]);
    $topRegistrationClient = issue66LookupClient(['name' => 'Top Registration Client']);
    $vehicleRegistrationClient = issue66LookupClient(['name' => 'JSON Registration Client']);
    $storageNumberDecoy = issue66LookupClient(['name' => 'Storage Number Decoy']);
    $unlinkedDecoy = issue66LookupClient(['name' => 'Unlinked Request Decoy']);

    issue66LookupRequest('SR-LOOKUP-TOP-66', [
        'client_id' => $topRegistrationClient->id,
        'registration_number' => 'TOP66001',
    ]);
    issue66LookupRequest('SR-LOOKUP-JSON-66', [
        'client_id' => $vehicleRegistrationClient->id,
        'vehicles' => [
            issue66LookupVehicle('First car', 'AA66001'),
            issue66LookupVehicle('Second car', 'Bb66002'),
            issue66LookupVehicle('Third car', 'cC66003'),
            issue66LookupVehicle('Fourth car', 'FUTURE663', 'last', '00042'),
        ],
    ]);
    issue66LookupRequest('SR-LOOKUP-STORAGE-DECOY-66', [
        'client_id' => $storageNumberDecoy->id,
        'vehicles' => [
            [
                ...issue66LookupVehicle('OTHER-JSON-FIELD-66', 'NOT-THE-PLATE', 'renew', 'FUTURE663'),
                'tire_size' => 'TIRE-FIELD-ONLY-66',
            ],
        ],
    ]);
    issue66LookupRequest('SR-LOOKUP-UNLINKED-66', [
        'client_id' => null,
        'name' => $unlinkedDecoy->name,
        'registration_number' => 'UNLINKED-TOP-ONLY-66',
        'vehicles' => [issue66LookupVehicle('Unlinked car', 'UNLINKED-JSON-ONLY-66')],
    ]);

    foreach ([
        ['Needle Name', $nameClient->id],
        ['needle-email-66@example.test', $emailClient->id],
        ['99 88 77 66', $phoneClient->id],
        ['TOP66001', $topRegistrationClient->id],
        ['bB66002', $vehicleRegistrationClient->id],
        ['Cc66003', $vehicleRegistrationClient->id],
        ['FUTURE663', $vehicleRegistrationClient->id],
    ] as [$query, $expectedId]) {
        $response = $this->actingAs($operations)
            ->getJson(route('admin.clients.lookup', ['q' => $query]))
            ->assertOk();

        expect(array_column($response->json('items'), 'id'))->toBe([$expectedId]);
    }

    $this->actingAs($operations)
        ->get(route('admin.clients.index', ['q' => 'FUTURE663']))
        ->assertOk()
        ->assertSee('JSON Registration Client')
        ->assertDontSee('Storage Number Decoy')
        ->assertDontSee('Unlinked Request Decoy');

    foreach (['OTHER-JSON-FIELD-66', 'TIRE-FIELD-ONLY-66', 'UNLINKED-TOP-ONLY-66', 'UNLINKED-JSON-ONLY-66'] as $query) {
        $this->actingAs($operations)
            ->getJson(route('admin.clients.lookup', ['q' => strtolower($query)]))
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->actingAs($operations)
            ->get(route('admin.clients.index', ['q' => strtolower($query)]))
            ->assertOk()
            ->assertDontSee('Storage Number Decoy')
            ->assertDontSee('Unlinked Request Decoy');
    }
});

/** @param array<string, mixed> $override */
function issue66LookupClient(array $override = []): Client
{
    static $sequence = 0;
    $sequence++;

    return Client::query()->create(array_replace([
        'name' => sprintf('Lookup Client %03d', $sequence),
        'phone' => sprintf('92%06d', $sequence),
        'phone_normalized' => sprintf('4792%06d', $sequence),
        'email' => null,
        'address' => null,
        'district' => null,
        'note' => null,
        'created_by_user_id' => null,
    ], $override));
}

/** @param array<string, mixed> $override */
function issue66LookupRequest(string $reference, array $override = []): ServiceRequest
{
    return ServiceRequest::query()->create(array_replace([
        'reference' => $reference,
        'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual,
        'service_slug' => 'issue-66-client-lookup',
        'fulfilment' => 'workshop',
        'name' => 'Lookup request snapshot',
        'email' => null,
        'phone' => '90000000',
        'phone_normalized' => '4790000000',
        'status' => ServiceRequestStatus::New,
    ], $override));
}

/** @return array<string, string|null> */
function issue66LookupVehicle(
    string $vehicle,
    string $registrationNumber,
    string $storagePlan = 'none',
    ?string $storageNumber = null,
): array {
    return [
        'vehicle' => $vehicle,
        'registration_number' => $registrationNumber,
        'tire_size' => '205/55 R16',
        'vehicle_type' => 'passenger',
        'rim_band' => 'r16-r18',
        'storage_plan' => $storagePlan,
        'storage_number' => $storageNumber,
    ];
}
