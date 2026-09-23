<?php

namespace App\Modules\ServiceBooking;

use App\Models\User;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ServiceRequestQuickAction
{
    public function __construct(private readonly ServiceRequestChangeRecorder $changes) {}

    /** @param array<string, mixed> $data */
    public function execute(ServiceRequest $request, User $actor, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($request, $actor, $data): ServiceRequest {
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $command = (string) $data['command'];
            $attributes = [];
            $audit = null;

            if ($command === 'status') {
                $status = (string) ($data['status'] ?? '');
                if ($locked->status === ServiceRequestStatus::Cancelled && $status !== ServiceRequestStatus::Cancelled->value) {
                    throw ValidationException::withMessages([
                        'status' => __('service_requests.validation.booked_reactivation'),
                    ]);
                }
                $attributes['status'] = $status;
            } elseif ($command === 'assigned_van') {
                if ($locked->fulfilment !== 'mobile') {
                    throw ValidationException::withMessages([
                        'assigned_van' => __('service_requests.validation.van_requires_mobile'),
                    ]);
                }
                $attributes['assigned_van'] = filled($data['assigned_van'] ?? null) ? (string) $data['assigned_van'] : null;
            } elseif ($command === 'admin_note') {
                $attributes['admin_note'] = filled($data['admin_note'] ?? null) ? trim((string) $data['admin_note']) : null;
            } elseif ($command === 'storage_number') {
                [$attributes, $audit] = $this->storageNumberChange($locked, $data);
            } else {
                throw ValidationException::withMessages([
                    'command' => __('service_requests.validation.command_invalid'),
                ]);
            }

            return $this->changes->apply(
                request: $locked,
                attributes: $attributes,
                actor: $actor,
                expectedVersion: (int) $data['expected_version'],
                commandId: (string) $data['command_id'],
                command: $command,
                auditChanges: $audit,
            );
        }, 3);
    }

    /** @param array<string, mixed> $data @return array{array<string, mixed>, array<string, mixed>} */
    private function storageNumberChange(ServiceRequest $request, array $data): array
    {
        $number = filled($data['storage_number'] ?? null) ? trim((string) $data['storage_number']) : null;
        $vehicles = is_array($request->vehicles) ? array_values($request->vehicles) : [];

        if ($vehicles === []) {
            $plan = ServiceRequestVehiclePayload::generalPlan(
                (bool) $request->dekkhotell,
                $request->dekkhotell_already_stored,
            );
            if (! in_array($plan, ['new', 'renew', 'last'], true)) {
                throw ValidationException::withMessages([
                    'storage_number' => __('service_requests.validation.storage_number_requires_active_plan'),
                ]);
            }

            return [
                ['dekkhotell_number' => $number],
                ['storage_number' => ['vehicle' => null, 'old' => $request->dekkhotell_number, 'new' => $number]],
            ];
        }

        $index = (int) ($data['vehicle_index'] ?? -1);
        if (! array_key_exists($index, $vehicles)) {
            throw ValidationException::withMessages([
                'vehicle_index' => __('service_requests.validation.vehicle_invalid'),
            ]);
        }
        if (! in_array($vehicles[$index]['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true)) {
            throw ValidationException::withMessages([
                'storage_number' => __('service_requests.validation.storage_number_requires_active_plan'),
            ]);
        }

        $old = filled($vehicles[$index]['storage_number'] ?? null)
            ? trim((string) $vehicles[$index]['storage_number'])
            : null;
        $vehicles[$index]['storage_number'] = $number;

        return [
            ['vehicles' => $vehicles],
            ['storage_number' => ['vehicle' => $index + 1, 'old' => $old, 'new' => $number]],
        ];
    }
}
