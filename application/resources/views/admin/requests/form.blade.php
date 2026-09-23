@php
    $editing = $request->exists;
    $hasVersionConflict = $editing && ($errors->has('expected_version')
        || (filled(old('command_id')) && filled(old('expected_version')) && (int) old('expected_version') !== (int) $request->operation_version));
    $submittedExpectedVersion = old('expected_version', $request->operation_version);
    $vehicles = old('vehicles', $request->vehicles ?? []);
    $vehicles = is_array($vehicles) ? array_values($vehicles) : [];
    if ($vehicles === []) {
        $vehicles = [[
            'vehicle' => $request->vehicle, 'registration_number' => $request->registration_number,
            'tire_size' => $request->tire_size, 'vehicle_type' => 'passenger',
            'rim_band' => 'unknown', 'storage_plan' => 'none', 'storage_number' => null,
        ]];
    }
    $vehicles = array_slice($vehicles, 0, \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES);
    $vehicleCount = max(1, count($vehicles));
    $currentService = old('service_slug', $request->service_slug ?: optional($serviceOptions->first())->slug);
    $currentFlow = $serviceOptions->firstWhere('slug', $currentService)?->request_flow;
    $currentIsSeasonal = ($currentFlow instanceof \BackedEnum ? $currentFlow->value : $currentFlow) === 'seasonal';
    $currentMode = old('booking_mode', $request->booking_slot_key ? 'exact' : ($request->preferred_period_starts_at ? 'period' : 'manual'));
    $currentStatus = old('status', $request->status?->value ?? 'new');
    $selectedAdditionalServices = old('additional_services', $request->additional_services ?? []);
    $today = now()->format('Y-m-d');
    $storedDate = $request->preferred_date?->format('Y-m-d');
    $storedPeriodStart = $request->preferred_period_starts_at?->setTimezone(config('service_booking.timezone'))->format('H:i');
    $storedPeriodEnd = $request->preferred_period_ends_at?->setTimezone(config('service_booking.timezone'))->format('H:i');
    $minimumDate = $editing && filled($storedDate) && $storedDate < $today ? $storedDate : $today;
    $generalStorageSlugs = ['omlegging-balansering', 'dekkreparasjon', 'tpms-sensor'];
    $currentSupportsGeneralStorage = in_array($currentService, $generalStorageSlugs, true);
    $storedGeneralPlan = \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan(
        (bool) $request->dekkhotell,
        $request->dekkhotell_already_stored,
    );
    $generalStoragePlan = old('storage_plan', $storedGeneralPlan);
    $generalStorageNumber = old('storage_number', $request->dekkhotell_number);
    $lockedVehicles = is_array($request->vehicles) ? array_values($request->vehicles) : [];
    $lockedSeasonalHasActiveStorage = collect($lockedVehicles)->contains(
        fn ($vehicle) => is_array($vehicle) && in_array($vehicle['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true),
    );
    $currentFulfilment = old('fulfilment', $request->fulfilment ?: 'workshop');
    $field = 'easytire-input mt-2 min-h-11 w-full rounded-lg px-3';
    $requestParameters = ['request' => $request];
    if (filled($returnTo ?? null)) $requestParameters['return_to'] = $returnTo;
    $formAction = $editing
        ? route('admin.service-requests.update', $requestParameters)
        : route('admin.service-requests.store', filled($returnTo ?? null) ? ['return_to' => $returnTo] : []);
    $backHref = $editing
        ? route('admin.service-requests.show', $requestParameters)
        : (filled($returnTo ?? null) ? $returnTo : route('admin.service-requests.index'));
@endphp

<x-layouts.admin :title="$editing ? __('service_requests.edit') : __('service_requests.create')">
    <div class="max-w-6xl space-y-8" data-admin-request-form data-initial-service="{{ $request->service_slug }}">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $backHref }}" data-testid="request-back">{{ filled($returnTo ?? null) ? __('operations_day.back_to_day') : __('service_requests.back') }}</a>
                <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('service_requests.manual_intake') }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $editing ? __('service_requests.edit') : __('service_requests.create') }}</h1>
                <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('service_requests.manual_intro') }}</p>
            </div>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 p-5 text-sm text-red-900" role="alert" data-testid="request-form-errors">
                <strong>{{ __('service_requests.validation_summary') }}</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($hasVersionConflict)
            @include('admin.requests.partials.full-conflict-comparison', [
                'request' => $request,
                'serviceOptions' => $serviceOptions,
                'clientOptions' => $clientOptions,
                'draftVehicles' => $vehicles,
            ])
        @endif

        @if($detailsLocked)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950" role="status">
                <strong>{{ __('service_requests.booked_details_locked_title') }}</strong>
                <p class="mt-1">{{ __('service_requests.booked_details_locked_intro') }}</p>
            </div>
        @endif

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,.85fr)]" method="post" action="{{ $formAction }}" data-seasonal-prices='@json($seasonalPrices)' @if($editing) data-status-full-form data-reference="{{ $request->reference }}" data-initial-status="{{ $request->status->value }}" @endif @if($hasVersionConflict) data-conflict-draft="true" @endif>
            @csrf
            @if($editing)
                @method('PUT')
                <input type="hidden" name="expected_version" value="{{ $hasVersionConflict ? $submittedExpectedVersion : $request->operation_version }}">
                <input type="hidden" name="command_id" value="{{ old('command_id', (string) Illuminate\Support\Str::uuid()) }}">
                @if(filled($returnTo ?? null))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            @else
                <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
            @endif
            @unless($detailsLocked)<input type="hidden" name="operational_form" value="1">@endunless

            <div class="space-y-6">
                <section class="easytire-card rounded-2xl p-6">
                    <h2 class="text-xl font-black">{{ __('service_requests.customer') }}</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div
                            class="sm:col-span-2"
                            data-client-lookup
                            data-client-lookup-url="{{ route('admin.clients.lookup') }}"
                            data-minimum-message="{{ __('service_requests.client_lookup_minimum') }}"
                            data-searching-message="{{ __('service_requests.client_lookup_searching') }}"
                            data-empty-message="{{ __('service_requests.client_lookup_empty') }}"
                            data-results-message="{{ __('service_requests.client_lookup_results') }}"
                            data-error-message="{{ __('service_requests.client_lookup_error') }}"
                        >
                            <label class="block text-sm font-bold" for="client-lookup-input">{{ __('service_requests.client_lookup_label') }}</label>
                            <input class="{{ $field }}" id="client-lookup-input" type="search" autocomplete="off" placeholder="{{ __('service_requests.client_lookup_hint') }}" data-client-lookup-input>
                            <label class="mt-4 block text-sm font-bold" for="client-id">{{ __('service_requests.linked_client') }}</label>
                            <select class="{{ $field }}" id="client-id" name="client_id" data-client-select @error('client_id') aria-invalid="true" aria-describedby="request-client-id-error" @enderror>
                                <option value="">{{ __('service_requests.no_linked_client') }}</option>
                                @foreach($clientOptions as $client)
                                    <option value="{{ $client->id }}" @selected((string) old('client_id', $request->client_id) === (string) $client->id)>{{ $client->name }} · {{ $client->phone }}</option>
                                @endforeach
                            </select>
                            @error('client_id')<span class="et-admin-field-error" id="request-client-id-error">{{ $message }}</span>@enderror
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                <span class="text-sm font-normal text-easytire-muted" data-client-lookup-status aria-live="polite">{{ __('service_requests.client_lookup_minimum') }}</span>
                                <button class="et-btn et-btn--outline" type="button" data-client-load-more hidden>{{ __('service_requests.client_lookup_more') }}</button>
                            </div>
                            <span class="mt-2 block text-sm font-normal text-easytire-muted">
                                <a class="font-bold text-easytire-brand underline" href="{{ route('admin.clients.create', $editing ? array_filter(['service_request' => $request->id, 'return_to' => $returnTo]) : []) }}">{{ __('service_requests.create_client_link') }}</a>
                                · {{ __('service_requests.client_snapshot_note') }}
                            </span>
                        </div>
                        <label class="block text-sm font-bold" for="request-name">{{ __('service_requests.form.name') }}<input class="{{ $field }}" id="request-name" name="name" value="{{ old('name', $request->name) }}" maxlength="120" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="request-name-error" @enderror>@error('name')<span class="et-admin-field-error" id="request-name-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold" for="request-phone">{{ __('service_requests.form.phone') }}<input class="{{ $field }}" id="request-phone" name="phone" value="{{ old('phone', $request->phone) }}" maxlength="24" required inputmode="tel" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="request-phone-error" @enderror>@error('phone')<span class="et-admin-field-error" id="request-phone-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold" for="request-email">{{ __('service_requests.form.email_optional') }}<input class="{{ $field }}" id="request-email" name="email" value="{{ old('email', $request->email) }}" maxlength="255" type="email" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="request-email-error" @enderror>@error('email')<span class="et-admin-field-error" id="request-email-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold" for="request-address">{{ __('service_requests.address') }}<input class="{{ $field }}" id="request-address" name="address" value="{{ old('address', $request->address) }}" maxlength="255" autocomplete="street-address" @error('address') aria-invalid="true" aria-describedby="request-address-error" @enderror>@error('address')<span class="et-admin-field-error" id="request-address-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold" for="request-district">{{ __('service_requests.district') }}<input class="{{ $field }}" id="request-district" name="district" value="{{ old('district', $request->district) }}" maxlength="120" @error('district') aria-invalid="true" aria-describedby="request-district-error" @enderror>@error('district')<span class="et-admin-field-error" id="request-district-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold sm:col-span-2" for="request-note">{{ __('service_requests.note') }}<textarea class="{{ $field }} min-h-28 py-3" id="request-note" name="note" maxlength="2000" @error('note') aria-invalid="true" aria-describedby="request-note-error" @enderror>{{ old('note', $request->note) }}</textarea>@error('note')<span class="et-admin-field-error" id="request-note-error">{{ $message }}</span>@enderror</label>
                    </div>
                </section>

                <fieldset class="easytire-card rounded-2xl p-6" @disabled($detailsLocked)>
                    <legend class="px-1 text-xl font-black">{{ __('service_requests.booking') }}</legend>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.service') }}
                            <select class="{{ $field }}" name="service_slug" required data-request-service @error('service_slug') aria-invalid="true" aria-describedby="request-service-error" @enderror>
                                @foreach($serviceOptions as $service)
                                    <option
                                        value="{{ $service->slug }}"
                                        data-flow="{{ $service->request_flow->value }}"
                                        data-workshop-only="{{ in_array($service->slug, ['omlegging-balansering', 'tpms-sensor'], true) ? '1' : '0' }}"
                                        data-mounting="{{ $service->slug === 'omlegging-balansering' ? '1' : '0' }}"
                                        data-requires-date="{{ $service->request_flow->value === 'seasonal' || in_array($service->slug, ['omlegging-balansering', 'bilbatteri'], true) ? '1' : '0' }}"
                                        data-requires-registration="{{ $service->slug === 'bilbatteri' ? '1' : '0' }}"
                                        data-requires-problem="{{ in_array($service->slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true) ? '1' : '0' }}"
                                        data-supports-addons="{{ in_array($service->slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true) ? '1' : '0' }}"
                                        data-supports-storage="{{ in_array($service->slug, $generalStorageSlugs, true) ? '1' : '0' }}"
                                        @selected($currentService === $service->slug)
                                    >{{ app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb }}</option>
                                @endforeach
                            </select>
                            @error('service_slug')<span class="et-admin-field-error" id="request-service-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.fulfilment') }}
                            <select class="{{ $field }}" name="fulfilment" required data-request-fulfilment @error('fulfilment') aria-invalid="true" aria-describedby="request-fulfilment-error" @enderror>
                                <option value="workshop" @selected(old('fulfilment', $request->fulfilment) === 'workshop')>{{ __('service_requests.fulfilment_workshop') }}</option>
                                <option value="mobile" data-mobile-option @selected(old('fulfilment', $request->fulfilment) === 'mobile')>{{ __('service_requests.fulfilment_mobile') }}</option>
                            </select>
                            @error('fulfilment')<span class="et-admin-field-error" id="request-fulfilment-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.booking_mode') }}
                            <select class="{{ $field }}" name="booking_mode" required data-booking-mode @error('booking_mode') aria-invalid="true" aria-describedby="request-booking-mode-error" @enderror>
                                <option value="manual" @selected($currentMode === 'manual')>{{ __('service_requests.booking_mode_manual') }}</option>
                                <option value="period" @selected($currentMode === 'period')>{{ __('service_requests.booking_mode_period') }}</option>
                                <option value="exact" @selected($currentMode === 'exact')>{{ __('service_requests.booking_mode_exact') }}</option>
                            </select>
                            @error('booking_mode')<span class="et-admin-field-error" id="request-booking-mode-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.preferred_date') }}<input class="{{ $field }}" name="preferred_date" value="{{ old('preferred_date', $storedDate) }}" type="date" min="{{ $minimumDate }}" data-request-date @error('preferred_date') aria-invalid="true" aria-describedby="request-date-error" @enderror>@error('preferred_date')<span class="et-admin-field-error" id="request-date-error">{{ $message }}</span>@enderror</label>
                        <label class="block text-sm font-bold" data-manual-window>{{ __('service_requests.preferred_window') }}<input class="{{ $field }}" name="preferred_window" value="{{ old('preferred_window', $request->preferred_window) }}" maxlength="64" placeholder="{{ __('service_requests.preferred_window_hint') }}" @error('preferred_window') aria-invalid="true" aria-describedby="request-window-error" @enderror>@error('preferred_window')<span class="et-admin-field-error" id="request-window-error">{{ $message }}</span>@enderror</label>
                        <div class="sm:col-span-2" data-preferred-period hidden>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <label class="block text-sm font-bold">{{ __('service_requests.period_start') }}<input class="{{ $field }}" name="preferred_time_start" value="{{ old('preferred_time_start', $storedPeriodStart) }}" type="time" disabled @error('preferred_time_start') aria-invalid="true" aria-describedby="request-period-start-error" @enderror>@error('preferred_time_start')<span class="et-admin-field-error" id="request-period-start-error">{{ $message }}</span>@enderror</label>
                                <label class="block text-sm font-bold">{{ __('service_requests.period_end') }}<input class="{{ $field }}" name="preferred_time_end" value="{{ old('preferred_time_end', $storedPeriodEnd) }}" type="time" disabled @error('preferred_time_end') aria-invalid="true" aria-describedby="request-period-end-error" @enderror>@error('preferred_time_end')<span class="et-admin-field-error" id="request-period-end-error">{{ $message }}</span>@enderror</label>
                            </div>
                            <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.period_notice') }}</p>
                        </div>
                        <label class="block text-sm font-bold sm:col-span-2" data-exact-slot hidden>{{ __('service_requests.exact_slot') }}
                            <select class="{{ $field }}" name="booking_slot_key" data-slot-select disabled @error('booking_slot_key') aria-invalid="true" aria-describedby="request-slot-error" @enderror><option value="">{{ __('service_requests.select_date_first') }}</option></select>
                            @error('booking_slot_key')<span class="et-admin-field-error" id="request-slot-error">{{ $message }}</span>@enderror
                            <span class="mt-2 block font-normal text-easytire-muted" data-slot-status aria-live="polite"></span>
                        </label>
                    </div>

                    <div class="mt-6 border-t border-easytire-border pt-6" data-mounting-fields hidden>
                        <label class="block text-sm font-bold">{{ __('service_requests.service_variant') }}
                            <select class="{{ $field }}" name="service_variant" data-service-variant disabled>
                                <option value="mounting_balancing" @selected(old('service_variant', $request->service_variant) === 'mounting_balancing')>{{ __('project.request.mounting_balancing') }}</option>
                                <option value="balancing_only" @selected(old('service_variant', $request->service_variant) === 'balancing_only')>{{ __('project.request.balancing_only') }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-6 border-t border-easytire-border pt-6" data-seasonal-fields hidden>
                        <h3 class="font-black">{{ __('service_requests.vehicles') }}</h3>
                        <input type="hidden" name="car_count" value="{{ $vehicleCount }}" data-car-count>
                        <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.form.vehicle_limit_hint', ['max' => \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES]) }}</p>
                        <div class="mt-4 space-y-5" data-admin-vehicles>
                            @for($vehicleIndex = 0; $vehicleIndex < \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES; $vehicleIndex++)
                                @php
                                    $vehicleData = $vehicles[$vehicleIndex] ?? [
                                        'vehicle' => '', 'registration_number' => '', 'tire_size' => '',
                                        'vehicle_type' => 'passenger', 'rim_band' => 'unknown',
                                        'storage_plan' => 'none', 'storage_number' => null,
                                    ];
                                    $number = $vehicleIndex + 1;
                                @endphp
                                <section class="border border-easytire-border p-4" data-vehicle="{{ $number }}" data-request-vehicle data-initial-storage-plan="{{ $vehicleData['storage_plan'] ?? 'none' }}" data-initial-registration="{{ $vehicleData['registration_number'] ?? '' }}" @if($vehicleIndex >= $vehicleCount) hidden @endif>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <h4 class="font-black">{{ __('service_requests.vehicle_number', ['number' => $number]) }}</h4>
                                        @if($vehicleIndex > 0)<button class="et-btn et-btn--link" type="button" data-remove-vehicle>{{ __('service_requests.form.remove_vehicle') }}</button>@endif
                                    </div>
                                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                        <label class="block text-sm font-bold">{{ __('service_requests.form.vehicle') }}<input class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][vehicle]" value="{{ $vehicleData['vehicle'] ?? '' }}" maxlength="120" data-vehicle-field="vehicle"></label>
                                        <label class="block text-sm font-bold">{{ __('service_requests.registration_number') }}<input class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][registration_number]" value="{{ $vehicleData['registration_number'] ?? '' }}" maxlength="32" data-vehicle-field="registration_number" data-storage-registration></label>
                                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.tire_size') }}<input class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][tire_size]" value="{{ $vehicleData['tire_size'] ?? '' }}" maxlength="64" data-vehicle-field="tire_size"></label>
                                        <label class="block text-sm font-bold">{{ __('service_requests.form.vehicle_type') }}<select class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][vehicle_type]" data-vehicle-field="vehicle_type">@foreach(['passenger','large_vehicle','six_wheels'] as $value)<option value="{{ $value }}" @selected(($vehicleData['vehicle_type'] ?? 'passenger') === $value)>{{ __('service_requests.form.'.$value) }}</option>@endforeach</select></label>
                                        <label class="block text-sm font-bold">{{ __('service_requests.form.rim_band') }}<select class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][rim_band]" data-vehicle-field="rim_band">@foreach(['unknown','r15','r16-r18','r19'] as $value)<option value="{{ $value }}" @selected(($vehicleData['rim_band'] ?? 'unknown') === $value)>{{ __('service_requests.form.rim_'.$value) }}</option>@endforeach</select></label>
                                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.form.storage_plan') }}<select class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][storage_plan]" data-vehicle-field="storage_plan" data-storage-plan>@foreach(['none','new','renew','last'] as $value)<option value="{{ $value }}" @selected(($vehicleData['storage_plan'] ?? 'none') === $value)>{{ __('service_requests.form.storage_'.$value) }}</option>@endforeach</select></label>
                                        @unless($detailsLocked)<label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.form.storage_number') }}<input class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][storage_number]" value="{{ $vehicleData['storage_number'] ?? '' }}" maxlength="64" data-vehicle-field="storage_number"><span class="mt-2 block font-normal text-easytire-muted">{{ __('service_requests.form.storage_number_hint') }}</span></label>@endunless
                                    </div>
                                </section>
                            @endfor
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            <button class="et-btn et-btn--outline" type="button" data-add-vehicle>{{ __('service_requests.form.add_vehicle') }}</button>
                            <span class="text-sm text-easytire-muted" data-vehicle-count-status aria-live="polite"></span>
                        </div>
                        <div class="mt-4 border-l-2 border-easytire-brand bg-easytire-brand/5 p-4 text-sm" data-exact-vehicle-limit hidden>{{ __('service_requests.form.exact_vehicle_limit') }}</div>
                    </div>

                    <div class="mt-6 grid gap-5 border-t border-easytire-border pt-6 sm:grid-cols-2" data-general-vehicle-fields>
                        <label class="block text-sm font-bold">{{ __('service_requests.vehicle') }}<input class="{{ $field }}" name="vehicle" value="{{ old('vehicle', $request->vehicle) }}" maxlength="120"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.registration_number') }}<input class="{{ $field }}" name="registration_number" value="{{ old('registration_number', $request->registration_number) }}" maxlength="32"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.tire_size') }}<input class="{{ $field }}" name="tire_size" value="{{ old('tire_size', $request->tire_size) }}" maxlength="64" data-tire-size></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.wheel_count') }}<input class="{{ $field }}" name="wheel_count" value="{{ old('wheel_count', $request->wheel_count) }}" min="1" max="4" type="number" data-wheel-count></label>
                        <div class="sm:col-span-2" data-general-storage data-initial-plan="{{ $storedGeneralPlan }}" data-initial-registration="{{ $request->registration_number }}" @if(! $currentSupportsGeneralStorage) hidden @endif>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <label class="block text-sm font-bold">{{ __('service_requests.form.storage_plan') }}
                                    <select class="{{ $field }}" name="storage_plan" data-general-storage-plan @disabled(! $currentSupportsGeneralStorage)>
                                        @if($editing && $storedGeneralPlan === 'unknown')<option value="unknown" @selected($generalStoragePlan === 'unknown')>{{ __('service_requests.form.storage_unknown') }}</option>@endif
                                        @foreach(['none','new','renew','last'] as $value)<option value="{{ $value }}" @selected($generalStoragePlan === $value)>{{ __('service_requests.form.storage_'.$value) }}</option>@endforeach
                                    </select>
                                </label>
                                @unless($detailsLocked)<label class="block text-sm font-bold">{{ __('service_requests.form.storage_number') }}<input class="{{ $field }}" name="storage_number" value="{{ $generalStorageNumber }}" maxlength="64" data-general-storage-number @disabled(! $currentSupportsGeneralStorage)><span class="mt-2 block font-normal text-easytire-muted">{{ __('service_requests.form.storage_number_hint') }}</span></label>@endunless
                            </div>
                            <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.form.storage_charge_note') }}</p>
                        </div>
                    </div>

                    <fieldset class="mt-6 border-t border-easytire-border pt-6" data-additional-services hidden>
                        <legend class="font-black">{{ __('service_requests.additional_services') }}</legend>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach(['air_filter', 'cabin_filter', 'wiper_blades'] as $additionalService)
                                <label class="flex min-h-11 items-center gap-3 border border-easytire-border px-4 py-3 text-sm font-bold">
                                    <input name="additional_services[]" type="checkbox" value="{{ $additionalService }}" @checked(in_array($additionalService, $selectedAdditionalServices, true)) disabled>
                                    <span>{{ __('project.request.'.$additionalService) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </fieldset>

                @if($detailsLocked && (($currentIsSeasonal && $lockedSeasonalHasActiveStorage) || (! $currentIsSeasonal && $currentSupportsGeneralStorage)))
                    <section class="easytire-card rounded-2xl p-6" data-testid="booked-storage-numbers">
                        <h2 class="text-xl font-black">{{ __('service_requests.booked_storage_numbers_title') }}</h2>
                        <p class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('service_requests.booked_storage_numbers_intro') }}</p>

                        @if($currentIsSeasonal)
                            <div class="mt-5 space-y-5">
                                @foreach($lockedVehicles as $vehicleIndex => $vehicleData)
                                    @foreach(['vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan'] as $identityField)
                                        <input type="hidden" name="vehicles[{{ $vehicleIndex }}][{{ $identityField }}]" value="{{ $vehicleData[$identityField] ?? '' }}">
                                    @endforeach
                                    @if(in_array($vehicleData['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true))
                                        <div class="border border-easytire-border p-4">
                                            <p class="font-black">{{ __('service_requests.vehicle_number', ['number' => $vehicleIndex + 1]) }} · {{ $vehicleData['vehicle'] ?? '' }}</p>
                                            <label class="mt-4 block text-sm font-bold">{{ __('service_requests.form.storage_number') }}
                                                <input class="{{ $field }}" name="vehicles[{{ $vehicleIndex }}][storage_number]" value="{{ old("vehicles.{$vehicleIndex}.storage_number", $vehicleData['storage_number'] ?? '') }}" maxlength="64" data-testid="booked-storage-number-{{ $vehicleIndex }}">
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <label class="mt-5 block text-sm font-bold">{{ __('service_requests.form.storage_number') }}
                                <input class="{{ $field }}" name="storage_number" value="{{ $generalStorageNumber }}" maxlength="64" data-testid="booked-storage-number-general">
                            </label>
                        @endif
                        <p class="mt-3 text-sm text-easytire-muted">{{ __('service_requests.form.storage_number_hint') }}</p>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="easytire-card rounded-2xl p-6 xl:sticky xl:top-28">
                    <h2 class="text-xl font-black">{{ __('service_requests.follow_up') }}</h2>
                    <div class="mt-5 space-y-5">
                        @if($editing)
                            <label class="block text-sm font-bold">{{ __('service_requests.status') }}<select class="{{ $field }}" name="status" required @error('status') aria-invalid="true" aria-describedby="request-status-error" @enderror>@foreach(['new','contacted','closed','cancelled'] as $status)<option value="{{ $status }}" @selected($currentStatus === $status)>{{ __('service_requests.status_'.$status) }}</option>@endforeach</select>@error('status')<span class="et-admin-field-error" id="request-status-error">{{ $message }}</span>@enderror</label>
                        @else
                            <div><span class="block text-sm font-bold">{{ __('service_requests.status') }}</span><p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.status_new') }}</p></div>
                        @endif
                        <label class="block text-sm font-bold" data-assigned-van-field @if($currentFulfilment !== 'mobile') hidden @endif>{{ __('service_requests.assigned_van') }}
                            <select class="{{ $field }}" name="assigned_van" data-assigned-van @disabled($currentFulfilment !== 'mobile') @error('assigned_van') aria-invalid="true" aria-describedby="request-assigned-van-error" @enderror>
                                @foreach(['unassigned' => '', 'van_1' => 'van_1', 'van_2' => 'van_2'] as $label => $value)<option value="{{ $value }}" @selected(old('assigned_van', $request->assigned_van) === $value)>{{ __('service_requests.assigned_van_'.$label) }}</option>@endforeach
                            </select>
                            <span class="mt-2 block font-normal text-easytire-muted">{{ __('service_requests.assigned_van_notice') }}</span>
                            @error('assigned_van')<span class="et-admin-field-error" id="request-assigned-van-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.admin_note') }}<textarea class="{{ $field }} min-h-40 py-3" name="admin_note" maxlength="4000" @error('admin_note') aria-invalid="true" aria-describedby="request-admin-note-error" @enderror>{{ old('admin_note', $request->admin_note) }}</textarea>@error('admin_note')<span class="et-admin-field-error" id="request-admin-note-error">{{ $message }}</span>@enderror</label>
                        <p class="text-sm leading-6 text-easytire-muted">{{ __('service_requests.manual_consent_note') }}</p>
                        @if($hasVersionConflict)@include('admin.requests.partials.conflict-resolution', ['currentVersion' => $request->operation_version])@endif
                        <button class="et-btn et-btn--primary w-full" type="submit" data-testid="request-save">{{ $editing ? __('service_requests.save') : __('service_requests.create_action') }}</button>
                    </div>
                </section>
            </aside>
        </form>
    </div>

    @unless($detailsLocked)
        <script>
            (() => {
                const root = document.querySelector('[data-admin-request-form]');
                if (!root) return;
                const service = root.querySelector('[data-request-service]');
                const fulfilment = root.querySelector('[data-request-fulfilment]');
                const mode = root.querySelector('[data-booking-mode]');
                const date = root.querySelector('[data-request-date]');
                const manual = root.querySelector('[data-manual-window]');
                const period = root.querySelector('[data-preferred-period]');
                const exact = root.querySelector('[data-exact-slot]');
                const slots = root.querySelector('[data-slot-select]');
                const status = root.querySelector('[data-slot-status]');
                const seasonal = root.querySelector('[data-seasonal-fields]');
                const general = root.querySelector('[data-general-vehicle-fields]');
                const mounting = root.querySelector('[data-mounting-fields]');
                const variant = root.querySelector('[data-service-variant]');
                const additional = root.querySelector('[data-additional-services]');
                const mobileOption = root.querySelector('[data-mobile-option]');
                const address = root.querySelector('input[name="address"]');
                const registration = general?.querySelector('input[name="registration_number"]');
                const generalStorage = root.querySelector('[data-general-storage]');
                const generalStoragePlan = root.querySelector('[data-general-storage-plan]');
                const assignedVanField = root.querySelector('[data-assigned-van-field]');
                const assignedVan = root.querySelector('[data-assigned-van]');
                const problem = root.querySelector('textarea[name="note"]');
                const tireSize = root.querySelector('[data-tire-size]');
                const wheelCount = root.querySelector('[data-wheel-count]');
                const carCount = root.querySelector('[data-car-count]');
                const vehicleBlocks = [...root.querySelectorAll('[data-vehicle]')];
                const addVehicle = root.querySelector('[data-add-vehicle]');
                const vehicleCountStatus = root.querySelector('[data-vehicle-count-status]');
                const exactVehicleLimit = root.querySelector('[data-exact-vehicle-limit]');
                const endpoint = @json(route('storefront.booking.availability'));
                const oldSlot = @json(old('booking_slot_key', $request->booking_slot_key));

                const updateService = () => {
                    const selected = service?.selectedOptions[0];
                    const isSeasonal = selected?.dataset.flow === 'seasonal';
                    const isMounting = selected?.dataset.mounting === '1';
                    const requiresDate = selected?.dataset.requiresDate === '1';
                    const requiresRegistration = selected?.dataset.requiresRegistration === '1';
                    const requiresProblem = selected?.dataset.requiresProblem === '1';
                    const supportsAddons = selected?.dataset.supportsAddons === '1';
                    const supportsStorage = selected?.dataset.supportsStorage === '1';
                    const workshopOnly = selected?.dataset.workshopOnly === '1';
                    if (seasonal) seasonal.hidden = !isSeasonal;
                    if (general) general.hidden = isSeasonal;
                    if (mounting) mounting.hidden = !isMounting;
                    if (additional) additional.hidden = !supportsAddons;
                    if (seasonal) seasonal.querySelectorAll('input,select').forEach(el => el.disabled = !isSeasonal);
                    if (general) general.querySelectorAll('input,select').forEach(el => el.disabled = isSeasonal);
                    if (variant) {
                        variant.disabled = !isMounting;
                        variant.required = isMounting;
                    }
                    if (additional) additional.querySelectorAll('input').forEach(el => el.disabled = !supportsAddons);
                    if (generalStorage) {
                        generalStorage.hidden = !supportsStorage;
                        generalStorage.querySelectorAll('input,select').forEach(el => el.disabled = !supportsStorage);
                    }
                    if (mobileOption) mobileOption.disabled = workshopOnly;
                    if (workshopOnly && fulfilment?.value === 'mobile') fulfilment.value = 'workshop';
                    if (tireSize) tireSize.required = isMounting;
                    if (wheelCount) wheelCount.required = isMounting;
                    if (date) date.required = mode?.value === 'exact' || requiresDate;
                    if (registration) registration.dataset.serviceRequired = requiresRegistration ? '1' : '0';
                    if (problem) problem.required = requiresProblem;
                    updateFulfilment();
                    updateCars();
                    updateMode();
                    updateGeneralStorageRegistration();
                };
                const updateFulfilment = () => {
                    if (address) address.required = fulfilment?.value === 'mobile';
                    const mobile = fulfilment?.value === 'mobile';
                    if (assignedVanField) assignedVanField.hidden = !mobile;
                    if (assignedVan) assignedVan.disabled = !mobile;
                };
                const updateCars = () => {
                    if (!carCount || !vehicleBlocks.length) return;
                    const count = Math.max(1, Math.min(vehicleBlocks.length, Number(carCount.value || 1)));
                    carCount.value = String(count);
                    const seasonalEnabled = seasonal && !seasonal.hidden;
                    vehicleBlocks.forEach((vehicle, index) => {
                        const visible = seasonalEnabled && index < count;
                        vehicle.hidden = !visible;
                        vehicle.querySelectorAll('input,select,textarea').forEach(el => {
                            el.disabled = !visible;
                            if (el.dataset.vehicleField) {
                                el.required = visible && ['vehicle', 'vehicle_type', 'rim_band', 'storage_plan'].includes(el.dataset.vehicleField);
                            }
                        });
                    });
                    if (addVehicle) addVehicle.disabled = !seasonalEnabled || count >= vehicleBlocks.length;
                    if (vehicleCountStatus) vehicleCountStatus.textContent = `${count} / ${vehicleBlocks.length}`;
                    updateStorageRegistration();
                };
                const updateStorageRegistration = () => {
                    root.querySelectorAll('[data-vehicle]').forEach(vehicle => {
                        if (vehicle.hidden) return;
                        const plan = vehicle.querySelector('[data-storage-plan]');
                        const registration = vehicle.querySelector('[data-storage-registration]');
                        const selected = plan?.value !== 'none';
                        const changed = plan?.value !== (vehicle.dataset.initialStoragePlan || 'none');
                        const previouslyKnown = (vehicle.dataset.initialRegistration || '') !== '';
                        if (registration) registration.required = selected && (changed || previouslyKnown);
                    });
                };
                const updateGeneralStorageRegistration = () => {
                    const supportsStorage = !generalStorage?.hidden;
                    const selected = ['new', 'renew', 'last'].includes(generalStoragePlan?.value);
                    const changed = generalStoragePlan?.value !== generalStorage?.dataset.initialPlan;
                    const previouslyKnown = (generalStorage?.dataset.initialRegistration || '') !== '';
                    if (registration) {
                        registration.required = registration.dataset.serviceRequired === '1'
                            || (supportsStorage && selected && (changed || previouslyKnown));
                    }
                };
                const loadSlots = async () => {
                    if (mode?.value !== 'exact' || !date?.value) return;
                    slots.disabled = true;
                    slots.innerHTML = `<option value="">${@json(__('service_requests.loading_slots'))}</option>`;
                    status.textContent = '';
                    try {
                        const url = new URL(endpoint, window.location.origin);
                        url.searchParams.set('fulfilment', fulfilment.value);
                        url.searchParams.set('date', date.value);
                        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                        const body = await response.json();
                        const available = Array.isArray(body.slots) ? body.slots : [];
                        slots.innerHTML = `<option value="">${@json(__('service_requests.select_slot'))}</option>`;
                        available.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot.key;
                            option.textContent = `${slot.label} (${slot.remaining})`;
                            option.selected = slot.key === oldSlot;
                            slots.append(option);
                        });
                        slots.disabled = false;
                        status.textContent = available.length ? '' : @json(__('service_requests.no_slots'));
                    } catch (_) {
                        slots.innerHTML = `<option value="">${@json(__('service_requests.slots_failed'))}</option>`;
                        status.textContent = @json(__('service_requests.slots_failed'));
                    }
                };
                const updateMode = () => {
                    const tooManyVehicles = seasonal && !seasonal.hidden && Number(carCount?.value || 1) > 2;
                    const exactOption = mode?.querySelector('option[value="exact"]');
                    if (exactOption) exactOption.disabled = tooManyVehicles;
                    if (tooManyVehicles && mode?.value === 'exact') mode.value = 'period';
                    if (exactVehicleLimit) exactVehicleLimit.hidden = !tooManyVehicles;
                    const exactMode = mode?.value === 'exact';
                    const periodMode = mode?.value === 'period';
                    manual.hidden = exactMode || periodMode;
                    period.hidden = !periodMode;
                    exact.hidden = !exactMode;
                    manual.querySelectorAll('input').forEach(el => el.disabled = exactMode || periodMode);
                    period.querySelectorAll('input').forEach(el => { el.disabled = !periodMode; el.required = periodMode; });
                    slots.disabled = !exactMode;
                    date.required = exactMode || periodMode || service?.selectedOptions[0]?.dataset.requiresDate === '1';
                    if (exactMode) loadSlots();
                };
                service?.addEventListener('change', updateService);
                const resetVehicle = vehicle => {
                    vehicle.querySelectorAll('[data-vehicle-field]').forEach(field => {
                        const defaults = {vehicle_type: 'passenger', rim_band: 'unknown', storage_plan: 'none'};
                        field.value = defaults[field.dataset.vehicleField] || '';
                    });
                };
                const copyVehicle = (source, target) => {
                    target.querySelectorAll('[data-vehicle-field]').forEach(targetField => {
                        const sourceField = source.querySelector(`[data-vehicle-field="${targetField.dataset.vehicleField}"]`);
                        if (sourceField) targetField.value = sourceField.value;
                    });
                };
                addVehicle?.addEventListener('click', () => {
                    if (!carCount || Number(carCount.value) >= vehicleBlocks.length) return;
                    carCount.value = String(Number(carCount.value) + 1);
                    updateCars();
                    updateMode();
                });
                root.querySelectorAll('[data-remove-vehicle]').forEach(button => button.addEventListener('click', () => {
                    if (!carCount) return;
                    const count = Number(carCount.value || 1);
                    const removeIndex = vehicleBlocks.indexOf(button.closest('[data-vehicle]'));
                    if (removeIndex < 1 || removeIndex >= count) return;
                    for (let index = removeIndex; index < count - 1; index += 1) copyVehicle(vehicleBlocks[index + 1], vehicleBlocks[index]);
                    resetVehicle(vehicleBlocks[count - 1]);
                    carCount.value = String(Math.max(1, count - 1));
                    updateCars();
                    updateMode();
                }));
                root.querySelectorAll('[data-storage-plan]').forEach(el => el.addEventListener('change', updateStorageRegistration));
                generalStoragePlan?.addEventListener('change', updateGeneralStorageRegistration);
                mode?.addEventListener('change', updateMode);
                date?.addEventListener('change', loadSlots);
                fulfilment?.addEventListener('change', () => {
                    updateFulfilment();
                    loadSlots();
                });
                updateService();
                updateMode();
            })();
        </script>
    @endunless
</x-layouts.admin>
