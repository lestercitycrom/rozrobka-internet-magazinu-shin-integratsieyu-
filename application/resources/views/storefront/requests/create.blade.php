@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $serviceSlug = mb_strtolower((string) $service->slug);
    $seasonalSlugs = [
        'mobilt-dekkskift',
        'dekkverksted',
        'dekkskift',
        'dekkskift-tromso',
        'dekkskift-verksted',
        'hjulskift',
        'sesongskift',
    ];
    $persistedFlow = data_get($service, 'requestFlow');
    $persistedFlow = $persistedFlow instanceof \App\Modules\Content\Enums\ServiceRequestFlow
        ? $persistedFlow->value
        : (is_string($persistedFlow) ? mb_strtolower(trim($persistedFlow)) : null);
    $isSeasonal = in_array($persistedFlow, ['seasonal', 'generic'], true)
        ? $persistedFlow === 'seasonal'
        : in_array($serviceSlug, $seasonalSlugs, true);
    $requestedFulfilment = old('fulfilment', request()->query('fulfilment', 'workshop'));
    $requestedFulfilment = in_array($requestedFulfilment, ['workshop', 'mobile'], true) ? $requestedFulfilment : 'workshop';
    $requestedBookingMode = old('booking_mode', ($scheduleConfigured ?? false) ? 'exact' : 'period');
    $isMountingService = $serviceSlug === 'omlegging-balansering';
    $isBatteryService = $serviceSlug === 'bilbatteri';
    $isWorkshopOnly = in_array($serviceSlug, ['omlegging-balansering', 'tpms-sensor'], true);
    $supportsAdditionalServices = in_array($serviceSlug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
    if ($isWorkshopOnly) $requestedFulfilment = 'workshop';
    $requiresProblem = in_array($serviceSlug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
    $selectedAdditionalServices = old('additional_services', []);
    $selectedAdditionalServices = is_array($selectedAdditionalServices) ? $selectedAdditionalServices : [];
    $fitmentContext = session('vehicle_fitment.context');
    $contextIntent = is_array($fitmentContext) ? ($fitmentContext['intent'] ?? null) : null;
    $acceptsFitmentContext = ($contextIntent === 'packages' && $serviceSlug === 'dekk-felg-og-hjulpakker')
        || ($contextIntent === 'tpms' && $serviceSlug === 'tpms-sensor');
    $fitmentContext = $acceptsFitmentContext ? $fitmentContext : null;
    $prefillVehicle = is_array($fitmentContext) ? (string) ($fitmentContext['vehicle'] ?? '') : '';
    $prefillRegistration = is_array($fitmentContext) ? (string) ($fitmentContext['registration'] ?? '') : '';
    $prefillTireSize = is_array($fitmentContext) ? (string) ($fitmentContext['size'] ?? '') : '';
    $seasonalVehicles = old('vehicles');
    if (! is_array($seasonalVehicles) || $seasonalVehicles === []) {
        $legacyCount = max(1, min(2, (int) old('car_count', 1)));
        $seasonalVehicles = [];
        for ($legacyIndex = 0; $legacyIndex < $legacyCount; $legacyIndex++) {
            $suffix = $legacyIndex === 0 ? '' : '_2';
            $seasonalVehicles[] = [
                'vehicle' => old('vehicle'.$suffix, $legacyIndex === 0 ? $prefillVehicle : ''),
                'registration_number' => old('registration_number'.$suffix, $legacyIndex === 0 ? $prefillRegistration : ''),
                'tire_size' => old('tire_size'.$suffix, $legacyIndex === 0 ? $prefillTireSize : ''),
                'vehicle_type' => old('vehicle_type'.$suffix, 'passenger'),
                'rim_band' => old('rim_band'.$suffix, 'unknown'),
                'storage_plan' => old('storage_plan'.$suffix, $legacyIndex === 0 && old('dekkhotell') ? 'new' : 'none'),
            ];
        }
    }
    $seasonalVehicles = array_values(array_slice($seasonalVehicles, 0, \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES));
    $seasonalVehicleCount = max(1, count($seasonalVehicles));
@endphp

<x-layouts.storefront :title="__('booking.title')" active-section="services">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span><a href="{{ route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ $service->title }}</a></span><span>{{ __('booking.title') }}</span></div>
                <p class="et-eyebrow">{{ __('pro.booking.kicker') }}</p>
                <h1 class="et-h1">{{ $isSeasonal ? __('pro.booking.seasonal_title') : __('pro.booking.generic_title') }}</h1>
                <p class="et-lead">{{ $isSeasonal ? __('pro.booking.seasonal_intro') : __('pro.booking.generic_intro') }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact">
        <div class="et-container">
            @if (is_array($fitmentContext))
                <div class="et-alert et-alert--info et-fitment-request-context" data-testid="fitment-request-context">
                    <span class="et-alert__icon" aria-hidden="true">i</span>
                    <div>{{ __('fitment.request_context_notice', ['vehicle' => $prefillVehicle ?: $prefillRegistration, 'size' => $prefillTireSize]) }}</div>
                </div>
            @endif
            @if ($isSeasonal)
                <form method="post" action="{{ route($prefix.'requests.store', $service->slug) }}" class="et-booking-flow" data-production-booking data-seasonal-prices='@json($seasonalPrices ?? [])' data-enhanced="false" data-testid="seasonal-service-request">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                    <input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">
                    <input type="hidden" name="service_slug" value="{{ $service->slug }}">

                    <div class="et-booking-shell">
                        <div class="et-booking-card">
                            <div class="et-booking-card__head">
                                <p class="et-caption">{{ $service->title }}</p>
                                <h2 class="et-h3">{{ __('booking.title') }}</h2>
                            </div>
                            <div class="et-booking-card__body">
                                <div class="et-stepper" aria-label="{{ __('booking.title') }}" data-testid="seasonal-stepper">
                                    @foreach ([__('pro.booking.step_1'), __('pro.booking.step_2'), __('pro.booking.step_3'), __('pro.booking.step_4'), __('pro.booking.step_5')] as $index => $stepLabel)
                                        <div class="et-stepper__item" data-booking-step @if($index === 0) aria-current="step" @endif>
                                            <div class="et-stepper__bar"></div>
                                            <span class="et-stepper__label">{{ $stepLabel }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($errors->any())
                                    <div class="et-alert et-alert--error et-request-form-alert" role="alert" data-testid="request-errors">
                                        <span class="et-alert__icon" aria-hidden="true">!</span>
                                        <div><strong>{{ __('pro.booking.error_title') }}</strong><br>{{ $errors->first() }}</div>
                                    </div>
                                @endif

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-location">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.location_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.location_text') }}</p>
                                    <fieldset class="et-fieldset et-request-fulfilment" data-fulfilment-controller>
                                        <legend class="sr-only">{{ __('booking.fulfilment') }}</legend>
                                        <div class="et-radio-grid">
                                            <label class="et-radio-card">
                                                <input name="fulfilment" type="radio" value="workshop" required @checked($requestedFulfilment === 'workshop')>
                                                <span><strong>{{ __('booking.workshop') }}</strong><small>{{ __('pro.home.location_workshop_text') }}</small></span>
                                            </label>
                                            <label class="et-radio-card">
                                                <input name="fulfilment" type="radio" value="mobile" required @checked($requestedFulfilment === 'mobile')>
                                                <span><strong>{{ __('booking.mobile') }}</strong><small>{{ __('pro.home.location_mobile_text') }}</small></span>
                                            </label>
                                        </div>
                                        @error('fulfilment')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </fieldset>
                                     <div class="et-field et-request-mobile-address" data-mobile-address @if($requestedFulfilment !== 'mobile') hidden @endif>
                                        <label class="et-label" for="booking-address">{{ __('pro.booking.mobile_address_title') }}</label>
                                        <input class="et-input easytire-input" id="booking-address" name="address" type="text" value="{{ old('address') }}" autocomplete="street-address" @required($requestedFulfilment === 'mobile') aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">
                                         @error('address')<p class="et-form-error">{{ $message }}</p>@enderror
                                         <label class="et-label" for="booking-district">{{ __('service_requests.district') }}</label>
                                         <input class="et-input easytire-input" id="booking-district" name="district" type="text" value="{{ old('district') }}" maxlength="120" aria-invalid="{{ $errors->has('district') ? 'true' : 'false' }}">
                                         @error('district')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="et-step-actions"><span></span><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-vehicle-storage">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.vehicle_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.vehicle_text') }}</p>
                                    <input type="hidden" name="car_count" value="{{ $seasonalVehicleCount }}" data-car-count>
                                    <p class="et-help et-request-field-separated">{{ __('service_requests.form.vehicle_limit_hint', ['max' => \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES]) }}</p>

                                    <div data-seasonal-vehicles>
                                        @for ($vehicleIndex = 0; $vehicleIndex < \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::MAX_VEHICLES; $vehicleIndex++)
                                            @php
                                                $vehicle = $seasonalVehicles[$vehicleIndex] ?? [
                                                    'vehicle' => '', 'registration_number' => '', 'tire_size' => '',
                                                    'vehicle_type' => 'passenger', 'rim_band' => 'unknown', 'storage_plan' => 'none',
                                                ];
                                                $fieldPrefix = "vehicles.{$vehicleIndex}";
                                                $domPrefix = "booking-vehicle-{$vehicleIndex}";
                                            @endphp
                                            <fieldset class="et-form-section et-seasonal-vehicle" data-seasonal-vehicle data-request-vehicle data-vehicle-index="{{ $vehicleIndex }}" @if($vehicleIndex >= $seasonalVehicleCount) hidden @endif>
                                                <legend class="et-label et-seasonal-vehicle__legend">{{ __('service_requests.form.car_number', ['number' => $vehicleIndex + 1]) }}</legend>
                                                @if($vehicleIndex > 0)
                                                    <button class="et-btn et-btn--link et-seasonal-vehicle__remove" type="button" data-remove-vehicle>{{ __('service_requests.form.remove_vehicle') }}</button>
                                                @endif
                                                <div class="et-form-grid">
                                                    <div class="et-field">
                                                        <label class="et-label" for="{{ $domPrefix }}-model">{{ __('service_requests.form.vehicle') }} <span class="et-required">*</span></label>
                                                        <input class="et-input easytire-input" id="{{ $domPrefix }}-model" name="vehicles[{{ $vehicleIndex }}][vehicle]" value="{{ $vehicle['vehicle'] ?? '' }}" data-vehicle-field="vehicle" @required($vehicleIndex < $seasonalVehicleCount) autocomplete="off" aria-invalid="{{ $errors->has($fieldPrefix.'.vehicle') ? 'true' : 'false' }}">
                                                        <p class="et-help">{{ __('service_requests.form.vehicle_hint') }}</p>
                                                        @error($fieldPrefix.'.vehicle')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="et-field">
                                                        <label class="et-label" for="{{ $domPrefix }}-registration">{{ __('service_requests.form.registration_number') }}</label>
                                                        <input class="et-input easytire-input" id="{{ $domPrefix }}-registration" name="vehicles[{{ $vehicleIndex }}][registration_number]" value="{{ $vehicle['registration_number'] ?? '' }}" data-vehicle-field="registration_number" autocomplete="off" autocapitalize="characters" data-storage-registration aria-invalid="{{ $errors->has($fieldPrefix.'.registration_number') ? 'true' : 'false' }}">
                                                        <p class="et-help">{{ __('service_requests.form.registration_number_hint') }}</p>
                                                        @error($fieldPrefix.'.registration_number')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="et-field">
                                                        <label class="et-label" for="{{ $domPrefix }}-size">{{ __('service_requests.form.tire_size') }}</label>
                                                        <input class="et-input easytire-input" id="{{ $domPrefix }}-size" name="vehicles[{{ $vehicleIndex }}][tire_size]" value="{{ $vehicle['tire_size'] ?? '' }}" data-vehicle-field="tire_size" autocomplete="off" placeholder="205/55 R16" aria-invalid="{{ $errors->has($fieldPrefix.'.tire_size') ? 'true' : 'false' }}">
                                                        <p class="et-help">{{ __('service_requests.form.tire_size_hint') }}</p>
                                                        @error($fieldPrefix.'.tire_size')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="et-field">
                                                        <label class="et-label" for="{{ $domPrefix }}-type">{{ __('service_requests.form.vehicle_type') }}</label>
                                                        <select class="et-select easytire-input" id="{{ $domPrefix }}-type" name="vehicles[{{ $vehicleIndex }}][vehicle_type]" data-vehicle-field="vehicle_type" data-quote-field @required($vehicleIndex < $seasonalVehicleCount)>
                                                            @foreach (['passenger', 'large_vehicle', 'six_wheels'] as $type)<option value="{{ $type }}" @selected(($vehicle['vehicle_type'] ?? 'passenger') === $type)>{{ __('service_requests.form.'.$type) }}</option>@endforeach
                                                        </select>
                                                        @error($fieldPrefix.'.vehicle_type')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="et-field">
                                                        <label class="et-label" for="{{ $domPrefix }}-rim">{{ __('service_requests.form.rim_band') }}</label>
                                                        <select class="et-select easytire-input" id="{{ $domPrefix }}-rim" name="vehicles[{{ $vehicleIndex }}][rim_band]" data-vehicle-field="rim_band" data-quote-field @required($vehicleIndex < $seasonalVehicleCount)>
                                                            @foreach (['unknown', 'r15', 'r16-r18', 'r19'] as $band)<option value="{{ $band }}" @selected(($vehicle['rim_band'] ?? 'unknown') === $band)>{{ __('service_requests.form.rim_'.$band) }}</option>@endforeach
                                                        </select>
                                                        @error($fieldPrefix.'.rim_band')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div class="et-field et-field--full">
                                                        <label class="et-label" for="{{ $domPrefix }}-storage">{{ __('service_requests.form.storage_plan') }}</label>
                                                        <select class="et-select easytire-input" id="{{ $domPrefix }}-storage" name="vehicles[{{ $vehicleIndex }}][storage_plan]" data-vehicle-field="storage_plan" data-quote-field data-storage-plan @required($vehicleIndex < $seasonalVehicleCount)>
                                                            @foreach (['none', 'new', 'renew', 'last'] as $plan)<option value="{{ $plan }}" @selected(($vehicle['storage_plan'] ?? 'none') === $plan)>{{ __('service_requests.form.storage_'.$plan) }}</option>@endforeach
                                                        </select>
                                                        <p class="et-help">{{ __('service_requests.form.storage_charge_note') }}</p>
                                                        @error($fieldPrefix.'.storage_plan')<p class="et-form-error">{{ $message }}</p>@enderror
                                                    </div>
                                                </div>
                                            </fieldset>
                                        @endfor
                                    </div>

                                    <div class="et-step-actions et-seasonal-vehicle-actions">
                                        <button class="et-btn et-btn--outline" type="button" data-add-vehicle>{{ __('service_requests.form.add_vehicle') }}</button>
                                        <span data-vehicle-count-status aria-live="polite"></span>
                                    </div>
                                    <div class="et-alert et-alert--info et-request-notice" data-exact-vehicle-limit hidden><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('service_requests.form.exact_vehicle_limit') }}</div></div>

                                    <div class="et-alert et-alert--info et-request-notice" data-seasonal-quote data-exact-template="{{ __('service_requests.form.quote_exact') }}" data-range-template="{{ __('service_requests.form.quote_range') }}" data-unavailable-template="{{ __('service_requests.form.quote_unavailable') }}"><span class="et-alert__icon" aria-hidden="true">i</span><div data-seasonal-quote-text>{{ __('service_requests.form.quote_unavailable') }}</div></div>
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-time">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.time_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.time_text') }}</p>
                                    <div class="et-form-grid et-request-form-grid">
                                        <div class="et-field">
                                            <label class="et-label" for="booking-preferred-date">{{ __('booking.date') }}</label>
                                            <input class="et-input easytire-input" id="booking-preferred-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}" required aria-invalid="{{ $errors->has('preferred_date') ? 'true' : 'false' }}">
                                            @error('preferred_date')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-mode">{{ __('service_requests.booking_mode') }}</label>
                                            <select class="et-select easytire-input" id="booking-mode" name="booking_mode" required data-public-booking-mode>
                                                <option value="exact" @disabled(!($scheduleConfigured ?? false)) @selected($requestedBookingMode === 'exact')>{{ __('service_requests.booking_mode_exact') }}</option>
                                                <option value="period" @selected($requestedBookingMode === 'period')>{{ __('service_requests.booking_mode_period') }}</option>
                                            </select>
                                        </div>
                                        <div class="et-field et-field--full" data-booking-availability
                                             data-endpoint="{{ route($prefix.'booking.availability') }}"
                                             data-initial-configured="{{ ($scheduleConfigured ?? false) ? 'true' : 'false' }}"
                                             data-select-date="{{ __('booking_calendar.public.select_date') }}"
                                             data-loading="{{ __('booking_calendar.public.loading') }}"
                                             data-empty="{{ __('booking_calendar.public.empty') }}"
                                             data-load-error="{{ __('booking_calendar.public.load_error') }}"
                                             data-placeholder="{{ __('booking_calendar.public.choose_time') }}"
                                             data-manual-help="{{ __('booking_calendar.public.manual_help') }}"
                                             data-scheduled-help="{{ __('booking_calendar.public.scheduled_help') }}">
                                            <div data-booking-slots @if($requestedBookingMode !== 'exact' || !($scheduleConfigured ?? false)) hidden @endif>
                                                <label class="et-label" for="booking-slot-key">{{ __('booking_calendar.public.available_time') }}</label>
                                                <select class="et-select easytire-input" id="booking-slot-key" name="booking_slot_key" data-booking-slot-select data-old-slot="{{ old('booking_slot_key') }}" data-testid="booking-slot-select" @if($scheduleConfigured ?? false) required @else disabled @endif aria-invalid="{{ $errors->has('booking_slot_key') ? 'true' : 'false' }}">
                                                    <option value="">{{ __('booking_calendar.public.choose_time') }}</option>
                                                </select>
                                                <p class="et-help" data-booking-availability-status aria-live="polite">{{ __('booking_calendar.public.select_date') }}</p>
                                                <p class="et-help">{{ __('booking_calendar.public.scheduled_help') }}</p>
                                                @error('booking_slot_key')<p class="et-form-error">{{ $message }}</p>@enderror
                                            </div>
                                            <div data-booking-period @if($requestedBookingMode !== 'period') hidden @endif>
                                                <div class="et-form-grid">
                                                    <div class="et-field"><label class="et-label" for="booking-period-start">{{ __('service_requests.period_start') }}</label><input class="et-input easytire-input" id="booking-period-start" name="preferred_time_start" type="time" value="{{ old('preferred_time_start') }}" @required($requestedBookingMode === 'period') @disabled($requestedBookingMode !== 'period') aria-invalid="{{ $errors->has('preferred_time_start') ? 'true' : 'false' }}">@error('preferred_time_start')<p class="et-form-error">{{ $message }}</p>@enderror</div>
                                                    <div class="et-field"><label class="et-label" for="booking-period-end">{{ __('service_requests.period_end') }}</label><input class="et-input easytire-input" id="booking-period-end" name="preferred_time_end" type="time" value="{{ old('preferred_time_end') }}" @required($requestedBookingMode === 'period') @disabled($requestedBookingMode !== 'period') aria-invalid="{{ $errors->has('preferred_time_end') ? 'true' : 'false' }}">@error('preferred_time_end')<p class="et-form-error">{{ $message }}</p>@enderror</div>
                                                </div>
                                                <p class="et-help">{{ __('service_requests.period_notice') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="et-alert et-alert--info et-request-notice"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('booking.intro') }}</div></div>
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-contact">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.contact_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.contact_text') }}</p>
                                    <div class="et-form-grid et-request-form-grid">
                                        <div class="et-field">
                                            <label class="et-label" for="booking-name">{{ __('booking.name') }} <span class="et-required">*</span></label>
                                            <input class="et-input easytire-input" id="booking-name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" aria-describedby="booking-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                            @error('name')<p class="et-form-error" id="booking-name-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-email">{{ __('service_requests.form.email_optional') }}</label>
                                            <input class="et-input easytire-input" id="booking-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" aria-describedby="booking-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                            @error('email')<p class="et-form-error" id="booking-email-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-phone">{{ __('booking.phone') }} <span class="et-required">*</span></label>
                                            <input class="et-input easytire-input" id="booking-phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel" maxlength="24" pattern="\+?[0-9][0-9 ()-]{7,23}" aria-describedby="booking-phone-hint @error('phone') booking-phone-error @enderror" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                            <p class="et-help" id="booking-phone-hint">{{ __('phone_validation.hint') }}</p>
                                            @error('phone')<p class="et-form-error" id="booking-phone-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field et-field--full">
                                            <label class="et-label" for="booking-note">{{ __('booking.note') }}</label>
                                            <textarea class="et-textarea easytire-input" id="booking-note" name="note" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                                            @error('note')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-review">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.review_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.review_text') }}</p>
                                    <div class="et-alert et-alert--info et-request-review-note"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('pro.booking.summary_price') }}. {{ __('pro.booking.summary_price_note') }}</div></div>
                                    <label class="et-check et-request-consent">
                                        <input required name="consent" type="checkbox" value="1" @checked(old('consent'))>
                                        <span>{{ __('booking.consent') }}</span>
                                    </label>
                                    @error('consent')<p class="et-form-error">{{ $message }}</p>@enderror
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="submit" data-testid="seasonal-submit">{{ __('pro.booking.submit') }}</button></div>
                                </section>
                            </div>
                        </div>

                        <aside class="et-summary-card" aria-labelledby="booking-summary-title">
                            <p class="et-eyebrow et-eyebrow--light">{{ __('pro.booking.summary_title') }}</p>
                            <h2 class="et-h3 et-request-summary-title" id="booking-summary-title">{{ $service->title }}</h2>
                            <dl class="et-summary-list">
                                <div><dt>{{ __('pro.booking.summary_location') }}</dt><dd data-summary-field="fulfilment" data-empty-label="—">{{ $requestedFulfilment === 'mobile' ? __('booking.mobile') : __('booking.workshop') }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_vehicle') }}</dt><dd data-summary-field="vehicles[0][vehicle]" data-empty-label="—">{{ $seasonalVehicles[0]['vehicle'] ?? '—' }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_date') }}</dt><dd data-summary-field="preferred_date" data-empty-label="—">{{ old('preferred_date') ?: '—' }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_contact') }}</dt><dd data-summary-field="email" data-empty-label="—">{{ old('email') ?: '—' }}</dd></div>
                            </dl>
                            <div class="et-summary-price"><strong>{{ __('pro.booking.summary_price') }}</strong><small data-seasonal-summary-price>{{ __('service_requests.form.quote_unavailable') }}</small></div>
                        </aside>
                    </div>
                </form>
            @else
                <form method="post" action="{{ route($prefix.'requests.store', $service->slug) }}" class="et-generic-request" data-testid="generic-service-request">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
                    <input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">
                    <input type="hidden" name="service_slug" value="{{ $service->slug }}">

                    <div class="et-request-layout">
                        <aside class="et-request-context" data-testid="request-service-context" data-media-kind="{{ $serviceMedia['kind'] }}" data-media-source="{{ $serviceMedia['source'] }}">
                            <div class="et-request-context__media et-request-context__media--{{ $serviceMedia['crop'] }}">
                                @if ($serviceMedia['url'])
                                    <img src="{{ $serviceMedia['url'] }}" width="720" height="560" alt="{{ $serviceMedia['alt'] }}">
                                @else
                                    <div class="et-request-context__fallback">
                                        <img src="/brand/easytire-mark.svg" width="96" height="96" alt="">
                                    </div>
                                @endif
                            </div>
                            <div class="et-request-context__body">
                                <p class="et-eyebrow">{{ __('pro.booking.generic_service_label') }}</p>
                                <h2 class="et-h3">{{ $service->title }}</h2>
                                @if ($service->excerpt)<p class="et-copy">{{ $service->excerpt }}</p>@endif
                                <a class="et-btn et-btn--link" href="{{ route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ __('pro.home.service_link') }} <x-icon name="arrow" :size="18" /></a>
                            </div>
                        </aside>

                        <div class="et-booking-card et-request-form-card">
                        <div class="et-booking-card__head">
                            <p class="et-caption">{{ __('pro.booking.generic_service_label') }}</p>
                            <h2 class="et-h3">{{ $service->title }}</h2>
                        </div>
                        <div class="et-booking-card__body">
                            @if ($errors->any())
                                <div class="et-alert et-alert--error et-request-form-alert" role="alert" data-testid="request-errors"><span class="et-alert__icon" aria-hidden="true">!</span><div><strong>{{ __('pro.booking.error_title') }}</strong><br>{{ $errors->first() }}</div></div>
                            @endif

                            <p class="et-copy">{{ __('pro.booking.generic_form_intro') }}</p>
                            <div class="et-form-grid et-request-form-grid">
                                @if ($isMountingService)
                                    <div class="et-field">
                                        <label class="et-label" for="booking-service-variant">{{ __('project.request.variant') }} <span class="et-required">*</span></label>
                                        <select class="et-select easytire-input" id="booking-service-variant" name="service_variant" required aria-invalid="{{ $errors->has('service_variant') ? 'true' : 'false' }}">
                                            <option value="mounting_balancing" @selected(old('service_variant') === 'mounting_balancing')>{{ __('project.request.mounting_balancing') }}</option>
                                            <option value="balancing_only" @selected(old('service_variant') === 'balancing_only')>{{ __('project.request.balancing_only') }}</option>
                                        </select>
                                        @error('service_variant')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="et-field">
                                        <label class="et-label" for="booking-wheel-count">{{ __('project.request.wheel_count') }} <span class="et-required">*</span></label>
                                        <select class="et-select easytire-input" id="booking-wheel-count" name="wheel_count" required aria-invalid="{{ $errors->has('wheel_count') ? 'true' : 'false' }}">
                                            @foreach (range(1, 4) as $count)<option value="{{ $count }}" @selected((int) old('wheel_count', 4) === $count)>{{ $count }}</option>@endforeach
                                        </select>
                                        @error('wheel_count')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                                <div class="et-field">
                                    <label class="et-label" for="booking-fulfilment">{{ __('booking.fulfilment') }}</label>
                                    <select class="et-select easytire-input" id="booking-fulfilment" name="fulfilment" data-fulfilment-controller aria-invalid="{{ $errors->has('fulfilment') ? 'true' : 'false' }}">
                                        <option value="workshop" @selected($requestedFulfilment === 'workshop')>{{ __('booking.workshop') }}</option>
                                        @unless($isWorkshopOnly)<option value="mobile" @selected($requestedFulfilment === 'mobile')>{{ __('booking.mobile') }}</option>@endunless
                                    </select>
                                    @error('fulfilment')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                 <div class="et-field" data-mobile-address @if($requestedFulfilment !== 'mobile') hidden @endif>
                                    <label class="et-label" for="booking-address">{{ __('pro.booking.mobile_address_title') }}</label>
                                    <input class="et-input easytire-input" id="booking-address" name="address" value="{{ old('address') }}" autocomplete="street-address" @required($requestedFulfilment === 'mobile') aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">
                                     @error('address')<p class="et-form-error">{{ $message }}</p>@enderror
                                     <label class="et-label" for="booking-district">{{ __('service_requests.district') }}</label>
                                     <input class="et-input easytire-input" id="booking-district" name="district" value="{{ old('district') }}" maxlength="120" aria-invalid="{{ $errors->has('district') ? 'true' : 'false' }}">
                                     @error('district')<p class="et-form-error">{{ $message }}</p>@enderror
                                 </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-vehicle">{{ __('service_requests.form.vehicle') }}</label>
                                    <input class="et-input easytire-input" id="booking-vehicle" name="vehicle" value="{{ old('vehicle', $prefillVehicle) }}" autocomplete="off" aria-invalid="{{ $errors->has('vehicle') ? 'true' : 'false' }}">
                                    @error('vehicle')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-registration-number">{{ __('service_requests.form.registration_number') }} @if($isBatteryService)<span class="et-required">*</span>@endif</label>
                                    <input class="et-input easytire-input" id="booking-registration-number" name="registration_number" value="{{ old('registration_number', $prefillRegistration) }}" autocomplete="off" autocapitalize="characters" maxlength="32" @required($isBatteryService) aria-describedby="booking-registration-number-hint @error('registration_number') booking-registration-number-error @enderror" aria-invalid="{{ $errors->has('registration_number') ? 'true' : 'false' }}">
                                    <p class="et-help" id="booking-registration-number-hint">{{ $isBatteryService ? __('service_requests.form.battery_registration_hint') : __('service_requests.form.registration_number_hint') }}</p>
                                    @error('registration_number')<p class="et-form-error" id="booking-registration-number-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-tire-size">{{ __('service_requests.form.tire_size') }}</label>
                                    <input class="et-input easytire-input" id="booking-tire-size" name="tire_size" value="{{ old('tire_size', $prefillTireSize) }}" autocomplete="off" @required($isMountingService) aria-invalid="{{ $errors->has('tire_size') ? 'true' : 'false' }}">
                                    @error('tire_size')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-preferred-date">{{ __('booking.date') }} @if($isMountingService || $isBatteryService)<span class="et-required">*</span>@endif</label>
                                    <input class="et-input easytire-input" id="booking-preferred-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}" @required($isMountingService || $isBatteryService) @if($isBatteryService) aria-describedby="booking-preferred-date-hint @error('preferred_date') booking-preferred-date-error @enderror" @endif aria-invalid="{{ $errors->has('preferred_date') ? 'true' : 'false' }}">
                                    @if($isBatteryService)<p class="et-help" id="booking-preferred-date-hint">{{ __('service_requests.form.battery_date_hint') }}</p>@endif
                                    @error('preferred_date')<p class="et-form-error" id="booking-preferred-date-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-preferred-window">{{ __('booking.window') }}</label>
                                    <input class="et-input easytire-input" id="booking-preferred-window" name="preferred_window" value="{{ old('preferred_window') }}" aria-invalid="{{ $errors->has('preferred_window') ? 'true' : 'false' }}">
                                    @error('preferred_window')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-name">{{ __('booking.name') }} <span class="et-required">*</span></label>
                                    <input class="et-input easytire-input" id="booking-name" name="name" value="{{ old('name') }}" required autocomplete="name" aria-describedby="booking-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                    @error('name')<p class="et-form-error" id="booking-name-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-email">{{ __('service_requests.form.email_optional') }}</label>
                                    <input class="et-input easytire-input" id="booking-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" aria-describedby="booking-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                    @error('email')<p class="et-form-error" id="booking-email-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-phone">{{ __('booking.phone') }} <span class="et-required">*</span></label>
                                    <input class="et-input easytire-input" id="booking-phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel" maxlength="24" pattern="\+?[0-9][0-9 ()-]{7,23}" aria-describedby="booking-phone-hint @error('phone') booking-phone-error @enderror" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                    <p class="et-help" id="booking-phone-hint">{{ __('phone_validation.hint') }}</p>
                                    @error('phone')<p class="et-form-error" id="booking-phone-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field et-field--full">
                                    <label class="et-label" for="booking-note">{{ __('project.request.problem') }} @if($requiresProblem)<span class="et-required">*</span>@endif</label>
                                    <textarea class="et-textarea easytire-input" id="booking-note" name="note" placeholder="{{ __('project.request.problem_placeholder') }}" @required($requiresProblem) aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                                    @error('note')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                @if ($supportsAdditionalServices)
                                    <fieldset class="et-field et-field--full">
                                        <legend class="et-label">{{ __('project.request.additional_services') }}</legend>
                                        <div class="et-radio-grid et-request-addons">
                                            @foreach (['air_filter', 'cabin_filter', 'wiper_blades'] as $additionalService)
                                                <label class="et-radio-card">
                                                    <input name="additional_services[]" type="checkbox" value="{{ $additionalService }}" @checked(in_array($additionalService, $selectedAdditionalServices, true))>
                                                    <span><strong>{{ __('project.request.'.$additionalService) }}</strong><small>{{ __('project.request.'.$additionalService.'_help') }}</small></span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('additional_services')<p class="et-form-error">{{ $message }}</p>@enderror
                                        @error('additional_services.*')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </fieldset>
                                @endif
                            </div>

                            <div class="et-alert et-alert--info et-request-notice"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('booking.intro') }}</div></div>
                            <label class="et-check et-request-consent">
                                <input required name="consent" type="checkbox" value="1" @checked(old('consent')) aria-describedby="booking-consent-error" aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}">
                                <span>{{ __('booking.consent') }}</span>
                            </label>
                            @error('consent')<p class="et-form-error" id="booking-consent-error">{{ $message }}</p>@enderror
                            <button class="et-btn et-btn--primary et-request-submit" type="submit" data-testid="generic-submit">{{ __('pro.booking.submit') }}</button>
                        </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </section>
</x-layouts.storefront>
