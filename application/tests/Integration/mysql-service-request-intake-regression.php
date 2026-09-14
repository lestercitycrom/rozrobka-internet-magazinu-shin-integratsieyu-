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
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestCreator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const INTAKE_MIGRATION = '2026_09_14_000000_prepare_service_requests_for_manual_intake';
const CLIENT_CRM_MIGRATION = '2026_09_14_010000_add_client_crm_fields';
const VAN_ASSIGNMENT_MIGRATION = '2026_09_14_020000_add_assigned_van_to_service_requests';

function intakeAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';
    throw new RuntimeException($message.$suffix);
}

/** @param array<string, mixed> $arguments */
function intakeArtisan(string $command, array $arguments = []): void
{
    $exitCode = Artisan::call($command, $arguments);
    intakeAssert($exitCode === 0, "Artisan command {$command} failed.\n".Artisan::output(), $exitCode);
}

/** @return list<string> */
function intakeBaselineMigrations(): array
{
    return array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        static fn (string $path): bool => ! in_array(basename($path), [
            INTAKE_MIGRATION.'.php',
            CLIENT_CRM_MIGRATION.'.php',
            VAN_ASSIGNMENT_MIGRATION.'.php',
        ], true),
    ));
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
    ], $override);
}

function intakeAssertOldApplicationCanRead(ServiceRequest $request): void
{
    $oldPath = realpath((string) getenv('SCHEMA_REPAIR_OLD_APP_PATH'));
    intakeAssert(is_string($oldPath) && is_file($oldPath.'/bootstrap/app.php'), 'Retained old application fixture is unavailable.', $oldPath);

    $probe = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = App\Modules\ServiceBooking\Models\ServiceRequest::query()
    ->where('reference', getenv('INTAKE_COMPAT_REFERENCE'))
    ->firstOrFail();
$view = (new App\Http\Controllers\Admin\ServiceRequestController)->show($request);
echo json_encode([
    'view' => $view->name(),
    'reference' => $view->getData()['request']->reference,
    'email' => $view->getData()['request']->email,
], JSON_THROW_ON_ERROR);
PHP;

    $process = new Process([PHP_BINARY, '-r', $probe], $oldPath, [
        'INTAKE_COMPAT_REFERENCE' => $request->reference,
    ]);
    $process->setTimeout(60);
    $process->run();
    intakeAssert($process->isSuccessful(), 'Retained old application could not read the upgraded database.', $process->getErrorOutput());
    $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    intakeAssert($result === [
        'view' => 'admin.requests.show',
        'reference' => $request->reference,
        'email' => null,
    ], 'Retained old application returned an unexpected service-request contract.', $result);
}

try {
    $connection = DB::connection();
    $databaseName = (string) $connection->getDatabaseName();

    intakeAssert($connection->getDriverName() === 'mysql', 'This regression must run against real MySQL.', $connection->getDriverName());
    intakeAssert(app()->environment('testing'), 'This regression is restricted to APP_ENV=testing.', app()->environment());
    intakeAssert(str_contains(strtolower($databaseName), 'test'), 'The isolated database name must contain "test".', $databaseName);

    intakeArtisan('migrate:fresh', [
        '--path' => intakeBaselineMigrations(),
        '--realpath' => true,
        '--force' => true,
    ]);
    intakeAssert(! Schema::hasColumn('service_requests', 'source'), 'Baseline unexpectedly contains the intake migration.');

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
    $legacyToken = (string) Str::uuid();
    $legacyRequestId = (int) DB::table('service_requests')->insertGetId([
        'reference' => 'SR-PRE-57-MYSQL',
        'submission_token' => $legacyToken,
        'locale' => 'nb',
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'name' => 'Pre migration customer',
        'email' => 'legacy@example.test',
        'phone' => '900 00 000',
        'status' => 'new',
        'consent_at' => '2026-09-01 12:00:00',
        'created_at' => '2026-09-01 12:00:00',
        'updated_at' => '2026-09-01 12:00:00',
    ]);
    DB::table('service_request_submissions')->insert([
        'token' => $legacyToken,
        'service_request_id' => $legacyRequestId,
        'created_at' => '2026-09-01 12:00:00',
    ]);
    $before = (array) DB::table('service_requests')->find($legacyRequestId);

    intakeArtisan('migrate', ['--force' => true]);

    foreach (['source', 'created_by_user_id', 'phone_normalized'] as $column) {
        intakeAssert(Schema::hasColumn('service_requests', $column), "Missing service_requests.{$column}.");
    }
    intakeAssert(Schema::hasTable('clients'), 'Clients table was not created by the additive CRM migration.');
    foreach (['client_id', 'district', 'assigned_van', 'preferred_period_starts_at', 'preferred_period_ends_at'] as $column) {
        intakeAssert(Schema::hasColumn('service_requests', $column), "Missing service_requests.{$column}.");
    }
    foreach (['source', 'actor_user_id', 'session_fingerprint'] as $column) {
        intakeAssert(Schema::hasColumn('service_request_submissions', $column), "Missing service_request_submissions.{$column}.");
    }
    $after = (array) DB::table('service_requests')->find($legacyRequestId);
    foreach (['reference', 'email', 'phone', 'consent_at', 'created_at', 'updated_at'] as $column) {
        intakeAssert($after[$column] === $before[$column], "Populated row changed during intake migration: {$column}.", $after[$column]);
    }
    intakeAssert($after['source'] === 'public' && $after['created_by_user_id'] === null, 'Legacy request provenance was not backfilled safely.', $after);
    intakeAssert($after['phone_normalized'] === '4790000000', 'Legacy phone was not normalized.', $after['phone_normalized']);
    intakeAssert($after['client_id'] === null && $after['district'] === null, 'Legacy request was linked or assigned a district during migration.', $after);
    intakeAssert($after['assigned_van'] === null, 'Legacy request was assigned to a van during migration.', $after['assigned_van']);
    intakeAssert($after['preferred_period_starts_at'] === null && $after['preferred_period_ends_at'] === null, 'Legacy text window was guessed into structured timestamps.', $after);
    $legacySubmission = (array) DB::table('service_request_submissions')->where('token', $legacyToken)->first();
    intakeAssert($legacySubmission['source'] === 'public' && $legacySubmission['actor_user_id'] === null && $legacySubmission['session_fingerprint'] === null, 'Legacy submission provenance changed unexpectedly.', $legacySubmission);

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
    $date = $calendar->localDate(now($calendar->timezone())->addDay()->format('Y-m-d'));
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
    $client = Client::query()->create([
        'name' => 'MySQL CRM client',
        'phone' => '+47 933 33 333',
        'phone_normalized' => '4793333333',
        'email' => 'mysql-crm@example.test',
        'address' => 'Syntheticvegen 59',
        'district' => 'Tromsdalen',
        'created_by_user_id' => $operations->id,
    ]);
    $period = $creator->createManual(intakePayload($service, [
        'booking_mode' => 'period',
        'preferred_date' => $date->format('Y-m-d'),
        'preferred_time_start' => '12:00',
        'preferred_time_end' => '15:00',
        'preferred_window' => null,
        'client_id' => $client->id,
        'district' => 'Tromsdalen',
        'fulfilment' => 'mobile',
        'address' => 'Syntheticvegen 59',
        'assigned_van' => 'van_2',
        'name' => 'MySQL request snapshot',
        'phone' => '+47 944 44 444',
    ]), $operations, 'nb');
    intakeAssert($period->client_id === $client->id, 'Manual request was not linked to its explicit client.');
    intakeAssert($period->booking_slot_key === null && $period->booking_starts_at === null, 'Preferred period occupied a booking slot.');
    intakeAssert($period->preferred_window === '12:00–15:00', 'Structured period label was not retained.', $period->preferred_window);
    intakeAssert($period->preferred_period_starts_at?->setTimezone($calendar->timezone())->format('H:i') === '12:00', 'Structured period start was not retained.');
    intakeAssert($period->district === 'Tromsdalen', 'Request district was not retained.');
    intakeAssert($period->assigned_van === 'van_2', 'Manual van assignment was not retained.', $period->assigned_van);
    $client->update(['name' => 'Changed MySQL CRM client', 'phone' => '+47 955 55 555', 'phone_normalized' => '4795555555']);
    $period->refresh();
    intakeAssert($period->name === 'MySQL request snapshot' && $period->phone === '+47 944 44 444', 'Editing a client rewrote the historical request snapshot.');
    intakeAssert(ServiceRequest::query()->count() === 4, 'Unexpected request count after upgrade and intake flows.', ServiceRequest::query()->count());
    intakeAssert(OutboxMessage::query()->count() === 0, 'Manual requests published external events after exact-slot intake.');

    intakeAssertOldApplicationCanRead($manual);

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'driver' => $connection->getDriverName(),
        'verified' => [
            'populated_pre_issue_57_upgrade' => true,
            'legacy_rows_preserved' => true,
            'legacy_provenance_backfilled' => true,
            'manual_optional_email' => true,
            'employee_provenance' => true,
            'role_boundaries' => true,
            'employee_scoped_idempotency' => true,
            'normalized_phone_search' => true,
            'unconfirmed_window' => true,
            'exact_slot_claim' => true,
            'no_manual_external_outbox' => true,
            'retained_old_application_read' => true,
            'client_crm_additive_upgrade' => true,
            'legacy_period_not_guessed' => true,
            'client_snapshot_independence' => true,
            'structured_unconfirmed_period' => true,
            'manual_nullable_van_assignment' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL service-request intake regression failed: {$exception->getMessage()}\n");
    exit(1);
}
