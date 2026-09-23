<?php

use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Security');

function ndiFixture(string $name): string
{
    return base_path('tests/Fixtures/Ndi/'.$name);
}

/**
 * Add the optimistic-locking envelope required by every admin request mutation.
 */
function versionedServiceRequestPayload(
    ServiceRequest $request,
    array $payload,
): array {
    return [
        ...$payload,
        'expected_version' => $request->fresh()->operation_version,
        'command_id' => (string) Str::uuid(),
    ];
}
