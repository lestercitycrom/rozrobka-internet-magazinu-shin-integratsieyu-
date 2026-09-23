<?php

namespace App\Modules\ServiceBooking;

use App\Models\User;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\Models\ServiceRequestEvent;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class ServiceRequestChangeRecorder
{
    public function alreadyApplied(
        ServiceRequest $request,
        User $actor,
        string $commandId,
        string $command,
    ): bool {
        $existing = ServiceRequestEvent::query()->where('command_id', $commandId)->first();
        if ($existing === null) {
            return false;
        }
        if ((int) $existing->service_request_id !== (int) $request->getKey()
            || (int) $existing->actor_user_id !== (int) $actor->getKey()
            || $existing->command !== $command) {
            throw ValidationException::withMessages([
                'command_id' => __('service_requests.validation.command_conflict'),
            ]);
        }

        return true;
    }

    public function assertExpected(ServiceRequest $request, int $expectedVersion): void
    {
        if ((int) $request->operation_version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'expected_version' => __('service_requests.validation.version_conflict'),
            ]);
        }
    }

    /**
     * Apply one already-authorized change while the caller holds a row lock.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $auditChanges
     */
    public function apply(
        ServiceRequest $request,
        array $attributes,
        User $actor,
        int $expectedVersion,
        string $commandId,
        string $command,
        ?array $auditChanges = null,
    ): ServiceRequest {
        if ($this->alreadyApplied($request, $actor, $commandId, $command)) {
            return $request->refresh();
        }

        $this->assertExpected($request, $expectedVersion);

        $dirty = [];
        foreach ($attributes as $key => $value) {
            $request->setAttribute($key, $value);
            if ($request->isDirty($key)) {
                $dirty[$key] = [
                    'old' => $request->getOriginal($key),
                    'new' => $request->getAttribute($key),
                ];
            }
        }

        if ($dirty === []) {
            return $request;
        }

        $request->operation_version = $expectedVersion + 1;
        $request->save();

        ServiceRequestEvent::query()->create([
            'service_request_id' => $request->getKey(),
            'actor_user_id' => $actor->getKey(),
            'command_id' => $commandId,
            'command' => $command,
            'changes' => $auditChanges ?? $this->minimalDiff($dirty),
            'created_at' => now(),
        ]);

        return $request->refresh();
    }

    /** @param array<string, array{old: mixed, new: mixed}> $dirty */
    private function minimalDiff(array $dirty): array
    {
        $safeValues = [
            'status', 'assigned_van', 'client_id', 'service_slug', 'fulfilment',
            'preferred_date', 'preferred_window', 'booking_slot_key',
            'booking_starts_at', 'booking_ends_at', 'dekkhotell_number',
        ];
        $result = [];

        foreach ($dirty as $field => $values) {
            $result[$field] = in_array($field, $safeValues, true)
                ? ['old' => $this->serializable($values['old']), 'new' => $this->serializable($values['new'])]
                : ['changed' => true];
        }

        return $result;
    }

    private function serializable(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return is_scalar($value) || $value === null ? $value : ['changed' => true];
    }
}
