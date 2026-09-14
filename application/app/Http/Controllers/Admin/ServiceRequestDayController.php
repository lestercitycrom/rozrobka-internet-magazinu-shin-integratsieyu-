<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestMapLink;
use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ServiceRequestDayController extends Controller
{
    public function __invoke(Request $request, ServiceRequestMapLink $maps): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'resource' => ['nullable', Rule::in(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'])],
            'include_history' => ['nullable', 'boolean'],
            'include_cancelled' => ['nullable', 'boolean'],
        ]);
        $timezone = (string) config('service_booking.timezone', 'Europe/Oslo');
        $today = CarbonImmutable::now($timezone)->format('Y-m-d');
        $date = (string) ($filters['date'] ?? $today);
        $resource = (string) ($filters['resource'] ?? 'all');
        $includeHistory = $request->boolean('include_history') || $request->boolean('include_cancelled');

        $query = ServiceRequest::query()
            ->with('client')
            ->whereDate('preferred_date', $date);
        if (! $includeHistory) {
            $query->whereNotIn('status', [
                ServiceRequestStatus::Closed->value,
                ServiceRequestStatus::Cancelled->value,
            ]);
        }

        $this->applyResource($query, $resource);

        $requests = $query
            ->orderByRaw('CASE WHEN booking_starts_at IS NULL AND preferred_period_starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(booking_starts_at, preferred_period_starts_at)')
            ->orderByRaw('CASE WHEN booking_starts_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get();

        $returnParameters = ['date' => $date, 'resource' => $resource];
        if ($includeHistory) {
            $returnParameters['include_history'] = 1;
        }
        $returnTo = route('admin.service-requests.day', $returnParameters, false);
        $preparation = $this->preparation($requests);

        $locale = app()->getLocale();
        $serviceTitles = ServicePage::query()
            ->whereIn('slug', $requests->pluck('service_slug')->filter()->unique())
            ->get(['slug', 'title_nb', 'title_ru'])
            ->mapWithKeys(fn (ServicePage $page): array => [
                $page->slug => $locale === 'ru' && filled($page->title_ru) ? $page->title_ru : $page->title_nb,
            ]);

        return view('admin.requests.day', compact(
            'requests', 'serviceTitles', 'maps', 'timezone', 'today', 'date', 'resource',
            'includeHistory', 'returnTo', 'preparation',
        ));
    }

    /** @param Builder<ServiceRequest> $query */
    private function applyResource(Builder $query, string $resource): void
    {
        match ($resource) {
            'workshop' => $query->where('fulfilment', 'workshop'),
            'van_1', 'van_2' => $query->where('fulfilment', 'mobile')->where('assigned_van', $resource),
            'mobile_unassigned' => $query->where('fulfilment', 'mobile')->whereNull('assigned_van'),
            default => null,
        };
    }

    /**
     * Build the preparation list from the exact collection rendered below it.
     * Closed and cancelled rows remain historical context, never work instructions.
     *
     * @param  Collection<int, ServiceRequest>  $requests
     * @return array{confirmed: array{request_count: int, actions: list<array<string, mixed>>}, pending: array{request_count: int, actions: list<array<string, mixed>>}}
     */
    private function preparation(Collection $requests): array
    {
        $groups = [
            'confirmed' => ['request_count' => 0, 'actions' => []],
            'pending' => ['request_count' => 0, 'actions' => []],
        ];

        foreach ($requests as $item) {
            if (! in_array($item->status, [ServiceRequestStatus::New, ServiceRequestStatus::Contacted], true)) {
                continue;
            }

            $group = $item->booking_starts_at === null ? 'pending' : 'confirmed';
            $groups[$group]['request_count']++;

            foreach ($this->vehicles($item) as $index => $vehicle) {
                $storagePlan = (string) ($vehicle['storage_plan'] ?? 'unknown');
                if (! in_array($storagePlan, ['none', 'new', 'renew', 'last', 'unknown'], true)) {
                    $storagePlan = 'unknown';
                }
                if ($storagePlan === 'none') {
                    continue;
                }

                $storageNumber = filled($vehicle['storage_number'] ?? null)
                    ? trim((string) $vehicle['storage_number'])
                    : null;
                $groups[$group]['actions'][] = [
                    'request' => $item,
                    'vehicle_number' => $index + 1,
                    'vehicle' => $vehicle,
                    'storage_plan' => $storagePlan,
                    'storage_number' => $storageNumber,
                    'needs_storage_number' => ServiceRequestVehiclePayload::requiresStoredSet($storagePlan)
                        && $storageNumber === null,
                ];
            }
        }

        return $groups;
    }

    /** @return list<array<string, mixed>> */
    private function vehicles(ServiceRequest $request): array
    {
        $vehicles = is_array($request->vehicles) ? array_values($request->vehicles) : [];
        if ($vehicles !== []) {
            return $vehicles;
        }

        $hasStorageSignal = (bool) $request->dekkhotell
            || $request->dekkhotell_already_stored !== null
            || filled($request->dekkhotell_number);

        if (! filled($request->vehicle)
            && ! filled($request->registration_number)
            && ! filled($request->tire_size)
            && ! $hasStorageSignal) {
            return [];
        }

        return [[
            'vehicle' => $request->vehicle,
            'registration_number' => $request->registration_number,
            'tire_size' => $request->tire_size,
            'storage_plan' => $hasStorageSignal
                ? ServiceRequestVehiclePayload::generalPlan(
                    (bool) $request->dekkhotell,
                    $request->dekkhotell_already_stored,
                )
                : 'none',
            'storage_number' => $request->dekkhotell_number,
        ]];
    }
}
