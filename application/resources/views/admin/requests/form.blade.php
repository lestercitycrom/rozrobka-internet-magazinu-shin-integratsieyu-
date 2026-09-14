@php
    $editing = $request->exists;
    $vehicles = old('vehicles', $request->vehicles ?? []);
    $vehicleOne = is_array($vehicles) ? ($vehicles[0] ?? []) : [];
    $vehicleTwo = is_array($vehicles) ? ($vehicles[1] ?? []) : [];
    $currentService = old('service_slug', $request->service_slug ?: optional($serviceOptions->first())->slug);
    $currentMode = old('booking_mode', $request->booking_slot_key ? 'exact' : ($request->preferred_period_starts_at ? 'period' : 'manual'));
    $currentStatus = old('status', $request->status?->value ?? 'new');
    $selectedAdditionalServices = old('additional_services', $request->additional_services ?? []);
    $today = now()->format('Y-m-d');
    $storedDate = $request->preferred_date?->format('Y-m-d');
    $storedPeriodStart = $request->preferred_period_starts_at?->setTimezone(config('service_booking.timezone'))->format('H:i');
    $storedPeriodEnd = $request->preferred_period_ends_at?->setTimezone(config('service_booking.timezone'))->format('H:i');
    $minimumDate = $editing && filled($storedDate) && $storedDate < $today ? $storedDate : $today;
    $field = 'easytire-input mt-2 min-h-11 w-full rounded-lg px-3';
@endphp

<x-layouts.admin :title="$editing ? __('service_requests.edit') : __('service_requests.create')">
    <div class="max-w-6xl space-y-8" data-admin-request-form>
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $editing ? route('admin.service-requests.show', $request) : route('admin.service-requests.index') }}">{{ __('service_requests.back') }}</a>
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

        @if($detailsLocked)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950" role="status">
                <strong>{{ __('service_requests.booked_details_locked_title') }}</strong>
                <p class="mt-1">{{ __('service_requests.booked_details_locked_intro') }}</p>
            </div>
        @endif

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,.85fr)]" method="post" action="{{ $editing ? route('admin.service-requests.update', $request) : route('admin.service-requests.store') }}" data-seasonal-prices='@json($seasonalPrices)'>
            @csrf
            @if($editing) @method('PUT') @else <input type="hidden" name="submission_token" value="{{ $submissionToken }}"> @endif
            @unless($detailsLocked)<input type="hidden" name="operational_form" value="1">@endunless

            <div class="space-y-6">
                <section class="easytire-card rounded-2xl p-6">
                    <h2 class="text-xl font-black">{{ __('service_requests.customer') }}</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.linked_client') }}
                            <select class="{{ $field }}" name="client_id" data-client-select>
                                <option value="">{{ __('service_requests.no_linked_client') }}</option>
                                @foreach($clientOptions as $client)
                                    <option value="{{ $client->id }}" @selected((string) old('client_id', $request->client_id) === (string) $client->id)>{{ $client->name }} · {{ $client->phone }}</option>
                                @endforeach
                            </select>
                            <span class="mt-2 block font-normal text-easytire-muted">
                                <a class="font-bold text-easytire-brand underline" href="{{ route('admin.clients.create', $editing ? ['service_request' => $request->id] : []) }}">{{ __('service_requests.create_client_link') }}</a>
                                · {{ __('service_requests.client_snapshot_note') }}
                            </span>
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.form.name') }}<input class="{{ $field }}" name="name" value="{{ old('name', $request->name) }}" maxlength="120" required autocomplete="name"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.form.phone') }}<input class="{{ $field }}" name="phone" value="{{ old('phone', $request->phone) }}" maxlength="24" required inputmode="tel" autocomplete="tel"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.form.email_optional') }}<input class="{{ $field }}" name="email" value="{{ old('email', $request->email) }}" maxlength="255" type="email" autocomplete="email"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.address') }}<input class="{{ $field }}" name="address" value="{{ old('address', $request->address) }}" maxlength="255" autocomplete="street-address"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.district') }}<input class="{{ $field }}" name="district" value="{{ old('district', $request->district) }}" maxlength="120"></label>
                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.note') }}<textarea class="{{ $field }} min-h-28 py-3" name="note" maxlength="2000">{{ old('note', $request->note) }}</textarea></label>
                    </div>
                </section>

                <fieldset class="easytire-card rounded-2xl p-6" @disabled($detailsLocked)>
                    <legend class="px-1 text-xl font-black">{{ __('service_requests.booking') }}</legend>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.service') }}
                            <select class="{{ $field }}" name="service_slug" required data-request-service>
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
                                        @selected($currentService === $service->slug)
                                    >{{ app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.fulfilment') }}
                            <select class="{{ $field }}" name="fulfilment" required data-request-fulfilment>
                                <option value="workshop" @selected(old('fulfilment', $request->fulfilment) === 'workshop')>{{ __('service_requests.fulfilment_workshop') }}</option>
                                <option value="mobile" data-mobile-option @selected(old('fulfilment', $request->fulfilment) === 'mobile')>{{ __('service_requests.fulfilment_mobile') }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.booking_mode') }}
                            <select class="{{ $field }}" name="booking_mode" required data-booking-mode>
                                <option value="manual" @selected($currentMode === 'manual')>{{ __('service_requests.booking_mode_manual') }}</option>
                                <option value="period" @selected($currentMode === 'period')>{{ __('service_requests.booking_mode_period') }}</option>
                                <option value="exact" @selected($currentMode === 'exact')>{{ __('service_requests.booking_mode_exact') }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold">{{ __('service_requests.preferred_date') }}<input class="{{ $field }}" name="preferred_date" value="{{ old('preferred_date', $storedDate) }}" type="date" min="{{ $minimumDate }}" data-request-date></label>
                        <label class="block text-sm font-bold" data-manual-window>{{ __('service_requests.preferred_window') }}<input class="{{ $field }}" name="preferred_window" value="{{ old('preferred_window', $request->preferred_window) }}" maxlength="64" placeholder="{{ __('service_requests.preferred_window_hint') }}"></label>
                        <div class="sm:col-span-2" data-preferred-period hidden>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <label class="block text-sm font-bold">{{ __('service_requests.period_start') }}<input class="{{ $field }}" name="preferred_time_start" value="{{ old('preferred_time_start', $storedPeriodStart) }}" type="time" disabled></label>
                                <label class="block text-sm font-bold">{{ __('service_requests.period_end') }}<input class="{{ $field }}" name="preferred_time_end" value="{{ old('preferred_time_end', $storedPeriodEnd) }}" type="time" disabled></label>
                            </div>
                            <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.period_notice') }}</p>
                        </div>
                        <label class="block text-sm font-bold sm:col-span-2" data-exact-slot hidden>{{ __('service_requests.exact_slot') }}
                            <select class="{{ $field }}" name="booking_slot_key" data-slot-select disabled><option value="">{{ __('service_requests.select_date_first') }}</option></select>
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
                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            <label class="block text-sm font-bold">{{ __('service_requests.form.car_count') }}<select class="{{ $field }}" name="car_count" data-car-count><option value="1" @selected((int) old('car_count', count($vehicles) ?: 1) === 1)>{{ __('service_requests.form.one_car') }}</option><option value="2" @selected((int) old('car_count', count($vehicles) ?: 1) === 2)>{{ __('service_requests.form.two_cars') }}</option></select></label>
                            <span></span>
                            @foreach([1 => $vehicleOne, 2 => $vehicleTwo] as $number => $vehicleData)
                                <div class="contents" data-vehicle="{{ $number }}" @if($number === 2) hidden @endif>
                                    <p class="font-black sm:col-span-2">{{ __('service_requests.vehicle_number', ['number' => $number]) }}</p>
                                    <label class="block text-sm font-bold">{{ __('service_requests.form.vehicle') }}<input class="{{ $field }}" name="{{ $number === 1 ? 'vehicle' : 'vehicle_2' }}" value="{{ old($number === 1 ? 'vehicle' : 'vehicle_2', $vehicleData['vehicle'] ?? ($number === 1 ? $request->vehicle : null)) }}" maxlength="120"></label>
                                    <label class="block text-sm font-bold">{{ __('service_requests.registration_number') }}<input class="{{ $field }}" name="{{ $number === 1 ? 'registration_number' : 'registration_number_2' }}" value="{{ old($number === 1 ? 'registration_number' : 'registration_number_2', $vehicleData['registration_number'] ?? ($number === 1 ? $request->registration_number : null)) }}" maxlength="32" data-storage-registration></label>
                                    <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.tire_size') }}<input class="{{ $field }}" name="{{ $number === 1 ? 'tire_size' : 'tire_size_2' }}" value="{{ old($number === 1 ? 'tire_size' : 'tire_size_2', $vehicleData['tire_size'] ?? ($number === 1 ? $request->tire_size : null)) }}" maxlength="64"></label>
                                    <label class="block text-sm font-bold">{{ __('service_requests.form.vehicle_type') }}<select class="{{ $field }}" name="{{ $number === 1 ? 'vehicle_type' : 'vehicle_type_2' }}">@foreach(['passenger','large_vehicle','six_wheels'] as $value)<option value="{{ $value }}" @selected(old($number === 1 ? 'vehicle_type' : 'vehicle_type_2', $vehicleData['vehicle_type'] ?? 'passenger') === $value)>{{ __('service_requests.form.'.$value) }}</option>@endforeach</select></label>
                                    <label class="block text-sm font-bold">{{ __('service_requests.form.rim_band') }}<select class="{{ $field }}" name="{{ $number === 1 ? 'rim_band' : 'rim_band_2' }}">@foreach(['unknown','r15','r16-r18','r19'] as $value)<option value="{{ $value }}" @selected(old($number === 1 ? 'rim_band' : 'rim_band_2', $vehicleData['rim_band'] ?? 'unknown') === $value)>{{ __('service_requests.form.rim_'.$value) }}</option>@endforeach</select></label>
                                    <label class="block text-sm font-bold sm:col-span-2">{{ __('service_requests.form.storage_plan') }}<select class="{{ $field }}" name="{{ $number === 1 ? 'storage_plan' : 'storage_plan_2' }}" data-storage-plan>@foreach(['none','new','renew'] as $value)<option value="{{ $value }}" @selected(old($number === 1 ? 'storage_plan' : 'storage_plan_2', $vehicleData['storage_plan'] ?? 'none') === $value)>{{ __('service_requests.form.storage_'.$value) }}</option>@endforeach</select></label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 border-t border-easytire-border pt-6 sm:grid-cols-2" data-general-vehicle-fields>
                        <label class="block text-sm font-bold">{{ __('service_requests.vehicle') }}<input class="{{ $field }}" name="vehicle" value="{{ old('vehicle', $request->vehicle) }}" maxlength="120"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.registration_number') }}<input class="{{ $field }}" name="registration_number" value="{{ old('registration_number', $request->registration_number) }}" maxlength="32"></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.tire_size') }}<input class="{{ $field }}" name="tire_size" value="{{ old('tire_size', $request->tire_size) }}" maxlength="64" data-tire-size></label>
                        <label class="block text-sm font-bold">{{ __('service_requests.wheel_count') }}<input class="{{ $field }}" name="wheel_count" value="{{ old('wheel_count', $request->wheel_count) }}" min="1" max="4" type="number" data-wheel-count></label>
                        <label class="flex min-h-11 items-center gap-3 text-sm font-bold sm:col-span-2"><input name="dekkhotell" value="1" type="checkbox" @checked(old('dekkhotell', $request->dekkhotell))>{{ __('service_requests.form.dekkhotell') }}</label>
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
            </div>

            <aside class="space-y-6">
                <section class="easytire-card rounded-2xl p-6 xl:sticky xl:top-28">
                    <h2 class="text-xl font-black">{{ __('service_requests.follow_up') }}</h2>
                    <div class="mt-5 space-y-5">
                        @if($editing)
                            <label class="block text-sm font-bold">{{ __('service_requests.status') }}<select class="{{ $field }}" name="status" required>@foreach(['new','contacted','closed','cancelled'] as $status)<option value="{{ $status }}" @selected($currentStatus === $status)>{{ __('service_requests.status_'.$status) }}</option>@endforeach</select></label>
                        @else
                            <div><span class="block text-sm font-bold">{{ __('service_requests.status') }}</span><p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.status_new') }}</p></div>
                        @endif
                        <label class="block text-sm font-bold">{{ __('service_requests.admin_note') }}<textarea class="{{ $field }} min-h-40 py-3" name="admin_note" maxlength="4000">{{ old('admin_note', $request->admin_note) }}</textarea></label>
                        <p class="text-sm leading-6 text-easytire-muted">{{ __('service_requests.manual_consent_note') }}</p>
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
                const problem = root.querySelector('textarea[name="note"]');
                const tireSize = root.querySelector('[data-tire-size]');
                const wheelCount = root.querySelector('[data-wheel-count]');
                const carCount = root.querySelector('[data-car-count]');
                const vehicleTwo = root.querySelector('[data-vehicle="2"]');
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
                    if (mobileOption) mobileOption.disabled = workshopOnly;
                    if (workshopOnly && fulfilment?.value === 'mobile') fulfilment.value = 'workshop';
                    if (tireSize) tireSize.required = isMounting;
                    if (wheelCount) wheelCount.required = isMounting;
                    if (date) date.required = mode?.value === 'exact' || requiresDate;
                    if (registration) registration.required = requiresRegistration;
                    if (problem) problem.required = requiresProblem;
                    updateFulfilment();
                    updateCars();
                };
                const updateFulfilment = () => {
                    if (address) address.required = fulfilment?.value === 'mobile';
                };
                const updateCars = () => {
                    if (!vehicleTwo) return;
                    const visible = seasonal && !seasonal.hidden && carCount?.value === '2';
                    vehicleTwo.hidden = !visible;
                    vehicleTwo.querySelectorAll('input,select').forEach(el => el.disabled = !visible);
                    updateStorageRegistration();
                };
                const updateStorageRegistration = () => {
                    root.querySelectorAll('[data-vehicle]').forEach(vehicle => {
                        if (vehicle.hidden) return;
                        const plan = vehicle.querySelector('[data-storage-plan]');
                        const registration = vehicle.querySelector('[data-storage-registration]');
                        if (registration) registration.required = plan?.value !== 'none';
                    });
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
                carCount?.addEventListener('change', updateCars);
                root.querySelectorAll('[data-storage-plan]').forEach(el => el.addEventListener('change', updateStorageRegistration));
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
