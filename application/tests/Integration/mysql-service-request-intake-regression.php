<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestCreator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function intakeAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';

    throw new RuntimeException($message.$suffix);
}

/** @return array<string, mixed> */
function intakePayload(ServicePage $service, array $override = []): array
{
    return array_replace([
        'submission_token' => (string) Str::uuid(),
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'booking_mode' => 'manual',
        'preferred_date' => now()->addDays(3)->format('Y-m-d'),
        'preferred_window' => 'Etter kl. 14',
        'name' => 'MySQL intake customer',
        'email' => null,
        'phone' => '99 88 77 66',
        'address' => null,
        'note' => 'MySQL integration regression',
        'admin_note' => 'Created by operations',
        'status' => 'new',
    ], $override);
}

try {
    $connection = DB::connection();
    $databaseName = (string) $connection->getDatabaseName();

    intakeAssert($connection->getDriverName() === 'mysql', 'This regression must run against real MySQL.', $connection->getDriverName());
    intakeAssert(app()->environment('testing'), 'This regression is restricted to APP_ENV=testing.', app()->environment());
    intakeAssert(str_contains(strtolower($databaseName), 'test'), 'The isolated database name must contain "test".', $databaseName);

    foreach (['source', 'created_by_user_id', 'phone_normalized'] as $column) {
        intakeAssert(Schema::hasColumn('service_requests', $column), "Missing service_requests.{$column}.");
    }
    foreach (['source', 'actor_user_id', 'session_fingerprint'] as $column) {
        intakeAssert(Schema::hasColumn('service_request_submissions', $column), "Missing service_request_submissions.{$column}.");
    }

    $service = ServicePage::query()->create([
        'slug' => 'mysql-intake-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Generic,
        'title_nb' => 'MySQL manuell tjeneste',
        'title_ru' => 'MySQL ручная услуга',
    ]);
    $operations = User::factory()->operations()->create();
    $otherOperations = User::factory()->operations()->create();
    $editor = User::factory()->editor()->create();
    $owner = User::factory()->owner()->create();

    intakeAssert($operations->canOperateServiceRequests(), 'Operations lost service-request access.');
    intakeAssert(! $editor->canOperateServiceRequests(), 'Editor gained service-request PII access.');
    intakeAssert($editor->canManageContent(), 'Editor lost content access.');
    intakeAssert($owner->isOwner() && $owner->canOperateServiceRequests(), 'Owner lost full administrative access.');

    /** @var ServiceRequestCreator $creator */
    $creator = app(ServiceRequestCreator::class);
    $payload = intakePayload($service);
    $manual = $creator->createManual($payload, $operations, 'nb');

    intakeAssert($manual->source === ServiceRequestSource::AdminManual, 'Manual request source was not retained.', $manual->source->value);
    intakeAssert($manual->created_by_user_id === $operations->id, 'Manual request creator was not retained.', $manual->created_by_user_id);
    intakeAssert($manual->email === null && $manual->consent_at === null, 'Manual request invented email or public consent.');
    intakeAssert($manual->phone_normalized === '4799887766', 'Norwegian phone normalization changed.', $manual->phone_normalized);
    intakeAssert($manual->booking_slot_key === null && $manual->preferred_window === 'Etter kl. 14', 'Unconfirmed scheduling data changed.');
    intakeAssert(OutboxMessage::query()->count() === 0, 'Manual intake published an external outbox event.');

    $replayed = $creator->createManual($payload, $operations, 'nb');
    intakeAssert($replayed->is($manual), 'Same-actor idempotent replay created another request.');

    try {
        $creator->createManual($payload, $otherOperations, 'nb');
        throw new RuntimeException('Another employee reused a submission token without a conflict.');
    } catch (ValidationException) {
        // Expected: submission tokens are scoped to the employee who created them.
    }

    $found = ServiceRequest::query()->where('phone_normalized', 'like', '%99887766%')->sole();
    intakeAssert($found->is($manual), 'Normalized phone lookup did not find the manual request.');

    $creator->updateManual($manual, [
        'name' => 'MySQL updated customer',
        'phone' => '+47 911 11 111',
        'status' => 'contacted',
        'admin_note' => 'Customer contacted',
    ]);
    $manual->refresh();
    intakeAssert($manual->name === 'MySQL updated customer', 'Editable customer data was not saved.');
    intakeAssert($manual->phone_normalized === '4791111111', 'Edited phone was not normalized.', $manual->phone_normalized);

    /** @var BookingCalendar $calendar */
    $calendar = app(BookingCalendar::class);
    $date = now($calendar->timezone())->addDay()->startOfDay();
    BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $slot = $calendar->availability('workshop', $date)['slots']->first();
    intakeAssert($slot !== null, 'No exact slot was generated for the MySQL regression.');

    $exact = $creator->createManual(intakePayload($service, [
        'booking_mode' => 'exact',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_window' => null,
        'booking_slot_key' => $slot->key,
        'phone' => '+47 922 22 222',
    ]), $operations, 'ru');
    intakeAssert($exact->booking_slot_key === $slot->key, 'Exact slot was not claimed atomically.');
    intakeAssert($exact->booking_starts_at !== null && $exact->booking_ends_at !== null, 'Exact slot timestamps were not retained.');
    intakeAssert(ServiceRequest::query()->count() === 2, 'Unexpected request count after idempotent and exact-slot flows.', ServiceRequest::query()->count());
    intakeAssert(OutboxMessage::query()->count() === 0, 'Manual requests published external events after exact-slot intake.');

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'driver' => $connection->getDriverName(),
        'verified' => [
            'mysql_schema' => true,
            'manual_optional_email' => true,
            'employee_provenance' => true,
            'role_boundaries' => true,
            'employee_scoped_idempotency' => true,
            'normalized_phone_search' => true,
            'unconfirmed_window' => true,
            'exact_slot_claim' => true,
            'no_manual_external_outbox' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL service-request intake regression failed: {$exception->getMessage()}\n");
    exit(1);
}
