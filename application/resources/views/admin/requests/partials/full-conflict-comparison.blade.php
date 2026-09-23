@php
    $empty = '—';
    $display = static fn (mixed $value): string => filled($value) ? (string) $value : '—';
    $translated = static function (string $prefix, mixed $value) use ($display): string {
        if (! filled($value)) return $display($value);
        $key = $prefix.$value;

        return Illuminate\Support\Facades\Lang::has($key) ? __($key) : (string) $value;
    };
    $serviceTitle = static function (?string $slug) use ($serviceOptions, $display): string {
        if (! filled($slug)) return $display($slug);
        $service = $serviceOptions->firstWhere('slug', $slug);

        return $service
            ? (app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb)
            : (string) $slug;
    };
    $clientTitle = static function (mixed $id) use ($request, $clientOptions): string {
        if (! filled($id)) return __('service_requests.no_linked_client');
        $client = (int) $request->client_id === (int) $id
            ? $request->client
            : $clientOptions->firstWhere('id', (int) $id);

        return ($client?->name ?: __('service_requests.linked_client')).' · #'.(int) $id;
    };
    $list = static fn (mixed $values): string => is_array($values) && $values !== []
        ? collect($values)->map(static fn ($value) => (string) $value)->join(', ')
        : '—';
    $vehicleList = static function (mixed $items): string {
        if (! is_array($items) || $items === []) return '—';

        return collect(array_values($items))->map(function ($vehicle, $index): string {
            if (! is_array($vehicle)) return __('service_requests.vehicle_number', ['number' => $index + 1]).': —';
            $plan = (string) ($vehicle['storage_plan'] ?? 'none');
            $planKey = 'service_requests.storage_operation_'.$plan;
            $planTitle = Illuminate\Support\Facades\Lang::has($planKey) ? __($planKey) : $plan;
            $vehicleType = (string) ($vehicle['vehicle_type'] ?? 'passenger');
            $vehicleTypeKey = 'service_requests.form.'.$vehicleType;
            $vehicleTypeTitle = Illuminate\Support\Facades\Lang::has($vehicleTypeKey) ? __($vehicleTypeKey) : $vehicleType;
            $rimBand = (string) ($vehicle['rim_band'] ?? 'unknown');
            $rimBandKey = 'service_requests.form.rim_'.$rimBand;
            $rimBandTitle = Illuminate\Support\Facades\Lang::has($rimBandKey) ? __($rimBandKey) : $rimBand;
            $parts = [
                $vehicle['vehicle'] ?? null,
                $vehicle['registration_number'] ?? null,
                $vehicle['tire_size'] ?? null,
                __('service_requests.form.vehicle_type').': '.$vehicleTypeTitle,
                __('service_requests.form.rim_band').': '.$rimBandTitle,
                $planTitle,
                filled($vehicle['storage_number'] ?? null)
                    ? __('service_requests.form.storage_number').': '.$vehicle['storage_number']
                    : null,
            ];

            return __('service_requests.vehicle_number', ['number' => $index + 1]).': '.collect($parts)->filter(fn ($value) => filled($value))->join(' · ');
        })->join("\n");
    };
    $currentMode = $request->booking_slot_key
        ? 'exact'
        : ($request->preferred_period_starts_at && $request->preferred_period_ends_at ? 'period' : 'manual');
    $draftMode = old('booking_mode', $currentMode);
    $timezone = config('service_booking.timezone');
    $currentTime = $currentMode === 'exact'
        ? collect([$request->booking_starts_at?->setTimezone($timezone)?->format('Y-m-d H:i'), $request->booking_ends_at?->setTimezone($timezone)?->format('H:i')])->filter()->join('–')
        : ($currentMode === 'period'
            ? collect([$request->preferred_period_starts_at?->setTimezone($timezone)?->format('H:i'), $request->preferred_period_ends_at?->setTimezone($timezone)?->format('H:i')])->filter()->join('–')
            : $request->preferred_window);
    $draftTime = $draftMode === 'exact'
        ? old('booking_slot_key', $request->booking_slot_key)
        : ($draftMode === 'period'
            ? collect([old('preferred_time_start'), old('preferred_time_end')])->filter()->join('–')
            : old('preferred_window', $request->preferred_window));
    $currentVehicles = $request->operationalVehicles();
    $draftClientId = old('client_id', $request->client_id);
    $draftWheelCount = $draftWheelCount ?? old('wheel_count', $request->wheel_count);
    $rows = [
        [__('service_requests.linked_client'), $clientTitle($request->client_id), $clientTitle($draftClientId)],
        [__('service_requests.form.name'), $display($request->name), $display(old('name', $request->name))],
        [__('service_requests.form.phone'), $display($request->phone), $display(old('phone', $request->phone))],
        [__('service_requests.form.email_optional'), $display($request->email), $display(old('email', $request->email))],
        [__('service_requests.address'), $display($request->address), $display(old('address', $request->address))],
        [__('service_requests.district'), $display($request->district), $display(old('district', $request->district))],
        [__('service_requests.note'), $display($request->note), $display(old('note', $request->note))],
        [__('service_requests.admin_note'), $display($request->admin_note), $display(old('admin_note', $request->admin_note))],
        [__('service_requests.service'), $serviceTitle($request->service_slug), $serviceTitle(old('service_slug', $request->service_slug))],
        [__('service_requests.service_variant'), $display($request->service_variant), $display(old('service_variant', $request->service_variant))],
        [__('service_requests.fulfilment'), $translated('service_requests.fulfilment_', $request->fulfilment), $translated('service_requests.fulfilment_', old('fulfilment', $request->fulfilment))],
        [__('service_requests.booking_mode'), $translated('service_requests.booking_mode_', $currentMode), $translated('service_requests.booking_mode_', $draftMode)],
        [__('service_requests.preferred_date'), $display($request->preferred_date?->format('Y-m-d')), $display(old('preferred_date', $request->preferred_date?->format('Y-m-d')))],
        [__('service_requests.preferred_window'), $display($currentTime), $display($draftTime)],
        [__('service_requests.assigned_van'), $translated('service_requests.assigned_van_', $request->assigned_van ?: 'unassigned'), $translated('service_requests.assigned_van_', old('assigned_van', $request->assigned_van) ?: 'unassigned')],
        [__('service_requests.status'), $translated('service_requests.status_', $request->status->value), $translated('service_requests.status_', old('status', $request->status->value))],
        [__('service_requests.form.vehicle'), $display($request->vehicle), $display(old('vehicle', $request->vehicle))],
        [__('service_requests.form.registration_number'), $display($request->registration_number), $display(old('registration_number', $request->registration_number))],
        [__('service_requests.form.tire_size'), $display($request->tire_size), $display(old('tire_size', $request->tire_size))],
        [__('service_requests.wheel_count'), $display($request->wheel_count), $display($draftWheelCount)],
        [__('service_requests.form.storage_plan'), $translated('service_requests.form.storage_', \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan((bool) $request->dekkhotell, $request->dekkhotell_already_stored)), $translated('service_requests.form.storage_', old('storage_plan', \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan((bool) $request->dekkhotell, $request->dekkhotell_already_stored)))],
        [__('service_requests.form.storage_number'), $display($request->dekkhotell_number), $display(old('storage_number', $request->dekkhotell_number))],
        [__('service_requests.additional_services'), $list($request->additional_services), $list(old('additional_services', $request->additional_services ?? []))],
        [__('service_requests.conflict_vehicles_and_storage'), $vehicleList($currentVehicles), $vehicleList($draftVehicles)],
    ];
@endphp

<section class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status" data-testid="request-form-version-conflict">
    <p class="font-bold">{{ __('service_requests.latest_version_notice', ['version' => $request->operation_version, 'status' => __('service_requests.status_'.$request->status->value)]) }}</p>
    <p class="mt-2">{{ __('service_requests.conflict_compare_intro') }}</p>
    <div class="mt-4 space-y-2" data-testid="request-form-current-values">
        <div class="hidden grid-cols-[minmax(10rem,.7fr)_minmax(0,1fr)_minmax(0,1fr)] gap-3 px-3 text-xs font-black uppercase tracking-wide sm:grid">
            <span>{{ __('service_requests.conflict_field') }}</span>
            <span>{{ __('service_requests.conflict_saved_now') }}</span>
            <span>{{ __('service_requests.conflict_your_draft') }}</span>
        </div>
        @foreach($rows as [$label, $currentValue, $draftValue])
            <dl class="grid gap-2 border p-3 sm:grid-cols-[minmax(10rem,.7fr)_minmax(0,1fr)_minmax(0,1fr)] sm:gap-3 {{ $currentValue !== $draftValue ? 'border-amber-500 bg-white' : 'border-amber-200 bg-amber-50/40' }}" @if($currentValue !== $draftValue) data-conflict-difference @endif>
                <div><dt class="font-black sm:hidden">{{ __('service_requests.conflict_field') }}</dt><dd class="font-bold">{{ $label }}</dd></div>
                <div><dt class="font-black sm:hidden">{{ __('service_requests.conflict_saved_now') }}</dt><dd class="whitespace-pre-wrap break-words">{{ $currentValue }}</dd></div>
                <div><dt class="font-black sm:hidden">{{ __('service_requests.conflict_your_draft') }}</dt><dd class="whitespace-pre-wrap break-words">{{ $draftValue }}</dd></div>
            </dl>
        @endforeach
    </div>
</section>
