<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Outbox\Models\OutboxMessage;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\BookingScheduleInterval;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\Models\ServiceRequestEvent;
use App\Modules\ServiceBooking\ServiceRequestRescheduler;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function rescheduleAssert(bool $condition, string $message, mixed $actual = null): void
{
    if ($condition) {
        return;
    }

    $suffix = func_num_args() >= 3 ? ' Actual: '.var_export($actual, true) : '';
    throw new RuntimeException($message.$suffix);
}

/** @param array<string, mixed> $overrides */
function rescheduleRequest(ServicePage $service, User $actor, string $reference, array $overrides = []): ServiceRequest
{
    return ServiceRequest::query()->create(array_replace([
        'reference' => $reference,
        'submission_token' => (string) Str::uuid(),
        'locale' => 'nb',
        'source' => ServiceRequestSource::AdminManual,
        'created_by_user_id' => $actor->id,
        'service_slug' => $service->slug,
        'fulfilment' => 'workshop',
        'preferred_date' => now('Europe/Oslo')->addDays(3)->format('Y-m-d'),
        'preferred_window' => 'Etter kl. 12',
        'vehicles' => [[
            'vehicle' => 'Volvo XC60',
            'registration_number' => $reference,
            'tire_size' => '235/55 R18',
            'vehicle_type' => 'passenger',
            'rim_band' => 'r16-r18',
            'storage_plan' => 'renew',
            'storage_number' => 'MYSQL-82',
        ]],
        'quote_min_minor' => 123400,
        'quote_max_minor' => 145600,
        'quote_currency' => 'NOK',
        'name' => 'MySQL reschedule customer',
        'email' => 'mysql-reschedule@example.test',
        'phone' => '90000082',
        'phone_normalized' => '4790000082',
        'address' => 'Ringvegen 200',
        'district' => 'Tromsø',
        'note' => 'Customer snapshot must survive',
        'admin_note' => 'Internal snapshot must survive',
        'status' => ServiceRequestStatus::New,
    ], $overrides));
}

function rescheduleChildCode(): string
{
    return <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents((string) getenv('READY_FILE'), 'ready');
$deadline = microtime(true) + 20;
while (! is_file((string) getenv('GO_FILE'))) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Barrier timeout\n");
        exit(2);
    }
    usleep(20000);
}
try {
    $request = App\Modules\ServiceBooking\Models\ServiceRequest::query()->findOrFail((int) getenv('REQUEST_ID'));
    $actor = App\Models\User::query()->findOrFail((int) getenv('ACTOR_ID'));
    $updated = app(App\Modules\ServiceBooking\ServiceRequestRescheduler::class)->execute($request, $actor, [
        'booking_date' => (string) getenv('BOOKING_DATE'),
        'booking_slot_key' => (string) getenv('SLOT_KEY'),
        'expected_version' => 1,
        'command_id' => (string) getenv('COMMAND_ID'),
    ]);
    echo json_encode(['status' => 'success', 'id' => $updated->id, 'version' => $updated->operation_version], JSON_THROW_ON_ERROR);
} catch (Illuminate\Validation\ValidationException $exception) {
    echo json_encode(['status' => 'validation', 'errors' => array_keys($exception->errors())], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(3);
}
PHP;
}

try {
    $connection = DB::connection();
    $databaseName = (string) $connection->getDatabaseName();
    rescheduleAssert($connection->getDriverName() === 'mysql', 'This regression must run against real MySQL.', $connection->getDriverName());
    rescheduleAssert(app()->environment('testing'), 'This regression is restricted to APP_ENV=testing.', app()->environment());
    rescheduleAssert(str_contains(strtolower($databaseName), 'test'), 'The isolated database name must contain "test".', $databaseName);

    rescheduleAssert(Artisan::call('migrate:fresh', ['--force' => true]) === 0, 'Fresh migrations failed.', Artisan::output());

    $service = ServicePage::query()->create([
        'slug' => 'mysql-reschedule-service',
        'sort_order' => 1,
        'publication_status' => ServicePagePublicationStatus::Published,
        'request_flow' => ServiceRequestFlow::Seasonal,
        'title_nb' => 'MySQL flytting',
        'title_ru' => 'MySQL перенос',
    ]);
    $operations = User::factory()->operations()->create();
    /** @var BookingCalendar $calendar */
    $calendar = app(BookingCalendar::class);
    $date = $calendar->localDate(now($calendar->timezone())->addDays(3)->format('Y-m-d'));
    $interval = BookingScheduleInterval::query()->create([
        'weekday' => $date->isoWeekday(),
        'location' => 'workshop',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'slot_minutes' => 30,
        'capacity' => 1,
        'is_active' => true,
    ]);
    $first = rescheduleRequest($service, $operations, 'SR-MYSQL-82-A');
    $second = rescheduleRequest($service, $operations, 'SR-MYSQL-82-B');
    $slots = $calendar->availability('workshop', $date)['slots'];
    $firstSlot = $slots->first();
    $secondSlot = $slots->skip(1)->first();
    rescheduleAssert($firstSlot !== null && $secondSlot !== null, 'The MySQL fixture did not produce two slots.');

    $barrierRoot = storage_path('framework/testing/mysql-reschedule-'.Str::uuid());
    rescheduleAssert(mkdir($barrierRoot, 0777, true), 'Could not create the isolated concurrency barrier.', $barrierRoot);
    $goFile = $barrierRoot.'/go';
    $processes = [];
    foreach ([$first, $second] as $index => $request) {
        $readyFile = $barrierRoot.'/ready-'.$index;
        $process = new Process([PHP_BINARY, '-r', rescheduleChildCode()], base_path(), [
            'REQUEST_ID' => (string) $request->id,
            'ACTOR_ID' => (string) $operations->id,
            'BOOKING_DATE' => $date->format('Y-m-d'),
            'SLOT_KEY' => $firstSlot->key,
            'COMMAND_ID' => (string) Str::uuid(),
            'READY_FILE' => $readyFile,
            'GO_FILE' => $goFile,
        ]);
        $process->setTimeout(60);
        $process->start();
        $processes[] = ['process' => $process, 'ready' => $readyFile];
    }

    $deadline = microtime(true) + 20;
    while (collect($processes)->contains(fn (array $entry): bool => ! is_file($entry['ready']))) {
        rescheduleAssert(microtime(true) <= $deadline, 'Concurrent workers did not reach the barrier.');
        usleep(20000);
    }
    file_put_contents($goFile, 'go');

    $results = [];
    foreach ($processes as $entry) {
        /** @var Process $process */
        $process = $entry['process'];
        $process->wait();
        rescheduleAssert($process->isSuccessful(), 'Concurrent reschedule worker failed.', $process->getErrorOutput());
        $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    rescheduleAssert(collect($results)->where('status', 'success')->count() === 1, 'Capacity-one contention did not produce one winner.', $results);
    rescheduleAssert(collect($results)->where('status', 'validation')->count() === 1, 'Capacity-one contention did not reject one loser.', $results);
    rescheduleAssert(ServiceRequest::query()->where('booking_slot_key', $firstSlot->key)->count() === 1, 'MySQL capacity one was overbooked.');
    rescheduleAssert(ServiceRequestEvent::query()->where('command', 'reschedule')->count() === 1, 'Concurrent claim wrote an unexpected event count.');

    /** @var ServiceRequest $winner */
    $winner = ServiceRequest::query()->where('booking_slot_key', $firstSlot->key)->sole();
    $loser = ServiceRequest::query()->whereKeyNot($winner->id)->sole();
    rescheduleAssert($loser->booking_slot_key === null && $loser->operation_version === 1, 'Rejected request was mutated.', $loser->toArray());
    $snapshot = $winner->only([
        'reference', 'client_id', 'service_slug', 'fulfilment', 'vehicles', 'quote_min_minor', 'quote_max_minor',
        'quote_currency', 'name', 'email', 'phone', 'address', 'district', 'note', 'admin_note',
    ]);
    $createdAt = $winner->created_at->toISOString();

    /** @var ServiceRequestRescheduler $rescheduler */
    $rescheduler = app(ServiceRequestRescheduler::class);
    $moveCommand = (string) Str::uuid();
    $rescheduler->execute($winner, $operations, [
        'booking_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $secondSlot->key,
        'expected_version' => 2,
        'command_id' => $moveCommand,
    ]);
    $winner->refresh();
    rescheduleAssert($winner->booking_slot_key === $secondSlot->key && $winner->operation_version === 3, 'Exact booking was not moved atomically.', $winner->toArray());
    rescheduleAssert($winner->only(array_keys($snapshot)) === $snapshot, 'Reschedule changed a protected customer/business snapshot.', $winner->only(array_keys($snapshot)));
    rescheduleAssert($winner->created_at->toISOString() === $createdAt, 'Reschedule changed the original creation time.', $winner->created_at);
    rescheduleAssert($calendar->availability('workshop', $date)['slots']->pluck('key')->contains($firstSlot->key), 'Moving did not release the old slot.');
    rescheduleAssert(! $calendar->availability('workshop', $date)['slots']->pluck('key')->contains($secondSlot->key), 'Moving did not consume the new slot.');

    $rescheduler->execute($winner, $operations, [
        'booking_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $secondSlot->key,
        'expected_version' => 2,
        'command_id' => $moveCommand,
    ]);
    rescheduleAssert($winner->fresh()->operation_version === 3, 'Idempotent replay advanced the version.');
    rescheduleAssert(ServiceRequestEvent::query()->where('command_id', $moveCommand)->count() === 1, 'Idempotent replay duplicated the audit event.');

    $eventCount = ServiceRequestEvent::query()->count();
    try {
        $rescheduler->execute($winner, $operations, [
            'booking_date' => $date->format('Y-m-d'),
            'booking_slot_key' => $firstSlot->key,
            'expected_version' => 2,
            'command_id' => (string) Str::uuid(),
        ]);
        throw new RuntimeException('A stale reschedule command was accepted.');
    } catch (ValidationException $exception) {
        rescheduleAssert(array_key_exists('expected_version', $exception->errors()), 'Stale reschedule failed for the wrong reason.', $exception->errors());
    }
    rescheduleAssert($winner->fresh()->booking_slot_key === $secondSlot->key, 'Stale command changed the booking.');
    rescheduleAssert(ServiceRequestEvent::query()->count() === $eventCount, 'Stale command wrote an audit event.');

    $noopCommand = (string) Str::uuid();
    $rescheduler->execute($winner->fresh(), $operations, [
        'booking_date' => $date->format('Y-m-d'),
        'booking_slot_key' => $secondSlot->key,
        'expected_version' => 3,
        'command_id' => $noopCommand,
    ]);
    rescheduleAssert($winner->fresh()->operation_version === 3, 'Same-slot no-op advanced the version.');
    rescheduleAssert(ServiceRequestEvent::query()->where('command_id', $noopCommand)->count() === 1, 'Same-slot command did not reserve its idempotency key.');

    foreach (ServiceRequestEvent::query()->where('command', 'reschedule')->get() as $event) {
        rescheduleAssert($event->actor_user_id === $operations->id, 'Audit actor was not retained.', $event->toArray());
        $encoded = json_encode($event->changes, JSON_THROW_ON_ERROR);
        rescheduleAssert(! str_contains($encoded, 'MySQL reschedule customer'), 'Audit JSON leaked customer name.', $event->changes);
        rescheduleAssert(! str_contains($encoded, '90000082'), 'Audit JSON leaked customer phone.', $event->changes);
    }
    rescheduleAssert(OutboxMessage::query()->count() === 0, 'Internal reschedule unexpectedly queued an external notification.');

    foreach (glob($barrierRoot.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($barrierRoot);

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'driver' => $connection->getDriverName(),
        'verified' => [
            'capacity_one_concurrent_claim' => true,
            'old_slot_release_and_new_slot_claim' => true,
            'optimistic_version_conflict' => true,
            'command_id_idempotency' => true,
            'same_slot_noop_idempotency' => true,
            'audit_actor_and_pii_redaction' => true,
            'business_snapshots_preserved' => true,
            'no_external_outbox' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "MySQL service-request reschedule regression failed: {$exception->getMessage()}\n");
    exit(1);
}
