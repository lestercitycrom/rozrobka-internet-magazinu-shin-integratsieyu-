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
    $isMountingService = $serviceSlug === 'omlegging-balansering';
    $isOilService = $serviceSlug === 'oljeskift';
    $requiresProblem = in_array($serviceSlug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
    $selectedAdditionalServices = old('additional_services', []);
    $selectedAdditionalServices = is_array($selectedAdditionalServices) ? $selectedAdditionalServices : [];
@endphp

<x-layouts.storefront :title="__('booking.title')" active-section="services">
    <section class="et-page-hero et-page-hero--light">
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
            @if ($isSeasonal)
                <form method="post" action="{{ route($prefix.'requests.store', $service->slug) }}" class="et-booking-flow" data-production-booking data-enhanced="false" data-testid="seasonal-service-request">
                    @csrf
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
                                    <div class="et-alert et-alert--error" role="alert" style="margin-bottom:1.2rem" data-testid="request-errors">
                                        <span class="et-alert__icon" aria-hidden="true">!</span>
                                        <div><strong>{{ __('pro.booking.error_title') }}</strong><br>{{ $errors->first() }}</div>
                                    </div>
                                @endif

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-location">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.location_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.location_text') }}</p>
                                    <fieldset class="et-fieldset" data-fulfilment-controller style="margin-top:1.2rem">
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
                                    <div class="et-field" data-mobile-address @if($requestedFulfilment !== 'mobile') hidden @endif style="margin-top:1rem">
                                        <label class="et-label" for="booking-address">{{ __('pro.booking.mobile_address_title') }}</label>
                                        <input class="et-input easytire-input" id="booking-address" name="address" type="text" value="{{ old('address') }}" autocomplete="street-address" @required($requestedFulfilment === 'mobile') aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">
                                        @error('address')<p class="et-form-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="et-step-actions"><span></span><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-vehicle-storage">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.vehicle_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.vehicle_text') }}</p>
                                    <div class="et-form-grid" style="margin-top:1.2rem">
                                        <div class="et-field">
                                            <label class="et-label" for="booking-vehicle">{{ __('service_requests.form.vehicle') }}</label>
                                            <input class="et-input easytire-input" id="booking-vehicle" name="vehicle" value="{{ old('vehicle') }}" autocomplete="off" aria-describedby="booking-vehicle-hint" aria-invalid="{{ $errors->has('vehicle') ? 'true' : 'false' }}">
                                            <p class="et-help" id="booking-vehicle-hint">{{ __('service_requests.form.vehicle_hint') }}</p>
                                            @error('vehicle')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-registration-number">{{ __('service_requests.form.registration_number') }}</label>
                                            <input class="et-input easytire-input" id="booking-registration-number" name="registration_number" value="{{ old('registration_number') }}" autocomplete="off" autocapitalize="characters" aria-describedby="booking-registration-number-hint" aria-invalid="{{ $errors->has('registration_number') ? 'true' : 'false' }}">
                                            <p class="et-help" id="booking-registration-number-hint">{{ __('service_requests.form.registration_number_hint') }}</p>
                                            @error('registration_number')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-tire-size">{{ __('service_requests.form.tire_size') }}</label>
                                            <input class="et-input easytire-input" id="booking-tire-size" name="tire_size" value="{{ old('tire_size') }}" autocomplete="off" aria-describedby="booking-tire-size-hint" aria-invalid="{{ $errors->has('tire_size') ? 'true' : 'false' }}">
                                            <p class="et-help" id="booking-tire-size-hint">{{ __('service_requests.form.tire_size_hint') }}</p>
                                            @error('tire_size')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <label class="et-radio-card">
                                            <input name="dekkhotell" type="checkbox" value="1" @checked(old('dekkhotell'))>
                                            <span><strong>{{ __('service_requests.form.dekkhotell') }}</strong><small>{{ __('pro.booking.storage_current_note') }}</small></span>
                                        </label>
                                    </div>
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-time">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.time_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.time_text') }}</p>
                                    <div class="et-form-grid" style="margin-top:1.2rem">
                                        <div class="et-field">
                                            <label class="et-label" for="booking-preferred-date">{{ __('booking.date') }}</label>
                                            <input class="et-input easytire-input" id="booking-preferred-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" aria-invalid="{{ $errors->has('preferred_date') ? 'true' : 'false' }}">
                                            @error('preferred_date')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-preferred-window">{{ __('booking.window') }}</label>
                                            <input class="et-input easytire-input" id="booking-preferred-window" name="preferred_window" type="text" value="{{ old('preferred_window') }}" aria-invalid="{{ $errors->has('preferred_window') ? 'true' : 'false' }}">
                                            @error('preferred_window')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                    <div class="et-alert et-alert--info" style="margin-top:1rem"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('booking.intro') }}</div></div>
                                    <div class="et-step-actions"><button class="et-btn et-btn--outline" type="button" data-booking-back>{{ __('pro.booking.back') }}</button><button class="et-btn et-btn--primary" type="button" data-booking-next>{{ __('pro.booking.next') }}</button></div>
                                </section>

                                <section class="et-step-panel" data-booking-panel data-testid="seasonal-step-contact">
                                    <h2 class="et-h3" tabindex="-1">{{ __('pro.booking.contact_title') }}</h2>
                                    <p class="et-copy">{{ __('pro.booking.contact_text') }}</p>
                                    <div class="et-form-grid" style="margin-top:1.2rem">
                                        <div class="et-field">
                                            <label class="et-label" for="booking-name">{{ __('booking.name') }} <span class="et-required">*</span></label>
                                            <input class="et-input easytire-input" id="booking-name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                            @error('name')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-email">{{ __('booking.email') }} <span class="et-required">*</span></label>
                                            <input class="et-input easytire-input" id="booking-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                            @error('email')<p class="et-form-error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="booking-phone">{{ __('booking.phone') }} <span class="et-required">*</span></label>
                                            <input class="et-input easytire-input" id="booking-phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                            @error('phone')<p class="et-form-error">{{ $message }}</p>@enderror
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
                                    <div class="et-alert et-alert--info" style="margin-top:1.1rem"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('pro.booking.summary_price') }}. {{ __('pro.booking.summary_price_note') }}</div></div>
                                    <label class="et-check" style="margin-top:1.2rem">
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
                            <h2 class="et-h3" id="booking-summary-title" style="margin-top:.5rem;color:#fff">{{ $service->title }}</h2>
                            <dl class="et-summary-list">
                                <div><dt>{{ __('pro.booking.summary_location') }}</dt><dd data-summary-field="fulfilment" data-empty-label="—">{{ $requestedFulfilment === 'mobile' ? __('booking.mobile') : __('booking.workshop') }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_vehicle') }}</dt><dd data-summary-field="vehicle" data-empty-label="—">{{ old('vehicle') ?: '—' }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_date') }}</dt><dd data-summary-field="preferred_date" data-empty-label="—">{{ old('preferred_date') ?: '—' }}</dd></div>
                                <div><dt>{{ __('pro.booking.summary_contact') }}</dt><dd data-summary-field="email" data-empty-label="—">{{ old('email') ?: '—' }}</dd></div>
                            </dl>
                            <div class="et-summary-price"><strong>{{ __('pro.booking.summary_price') }}</strong><small>{{ __('pro.booking.summary_price_note') }}</small></div>
                        </aside>
                    </div>
                </form>
            @else
                <form method="post" action="{{ route($prefix.'requests.store', $service->slug) }}" class="et-generic-request" data-testid="generic-service-request">
                    @csrf
                    <input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">
                    <input type="hidden" name="service_slug" value="{{ $service->slug }}">

                    <div class="et-booking-card">
                        <div class="et-booking-card__head">
                            <p class="et-caption">{{ __('pro.booking.generic_service_label') }}</p>
                            <h2 class="et-h3">{{ $service->title }}</h2>
                        </div>
                        <div class="et-booking-card__body">
                            @if ($errors->any())
                                <div class="et-alert et-alert--error" role="alert" style="margin-bottom:1.2rem" data-testid="request-errors"><span class="et-alert__icon" aria-hidden="true">!</span><div><strong>{{ __('pro.booking.error_title') }}</strong><br>{{ $errors->first() }}</div></div>
                            @endif

                            <p class="et-copy">{{ __('pro.booking.generic_form_intro') }}</p>
                            <div class="et-form-grid" style="margin-top:1.2rem">
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
                                        <option value="mobile" @selected($requestedFulfilment === 'mobile')>{{ __('booking.mobile') }}</option>
                                    </select>
                                    @error('fulfilment')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field" data-mobile-address @if($requestedFulfilment !== 'mobile') hidden @endif>
                                    <label class="et-label" for="booking-address">{{ __('pro.booking.mobile_address_title') }}</label>
                                    <input class="et-input easytire-input" id="booking-address" name="address" value="{{ old('address') }}" autocomplete="street-address" @required($requestedFulfilment === 'mobile') aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">
                                    @error('address')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-vehicle">{{ __('service_requests.form.vehicle') }}</label>
                                    <input class="et-input easytire-input" id="booking-vehicle" name="vehicle" value="{{ old('vehicle') }}" autocomplete="off" aria-invalid="{{ $errors->has('vehicle') ? 'true' : 'false' }}">
                                    @error('vehicle')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-registration-number">{{ __('service_requests.form.registration_number') }}</label>
                                    <input class="et-input easytire-input" id="booking-registration-number" name="registration_number" value="{{ old('registration_number') }}" autocomplete="off" autocapitalize="characters" aria-invalid="{{ $errors->has('registration_number') ? 'true' : 'false' }}">
                                    @error('registration_number')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-tire-size">{{ __('service_requests.form.tire_size') }}</label>
                                    <input class="et-input easytire-input" id="booking-tire-size" name="tire_size" value="{{ old('tire_size') }}" autocomplete="off" aria-invalid="{{ $errors->has('tire_size') ? 'true' : 'false' }}">
                                    @error('tire_size')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-preferred-date">{{ __('booking.date') }}</label>
                                    <input class="et-input easytire-input" id="booking-preferred-date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" aria-invalid="{{ $errors->has('preferred_date') ? 'true' : 'false' }}">
                                    @error('preferred_date')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-preferred-window">{{ __('booking.window') }}</label>
                                    <input class="et-input easytire-input" id="booking-preferred-window" name="preferred_window" value="{{ old('preferred_window') }}" aria-invalid="{{ $errors->has('preferred_window') ? 'true' : 'false' }}">
                                    @error('preferred_window')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-name">{{ __('booking.name') }} <span class="et-required">*</span></label>
                                    <input class="et-input easytire-input" id="booking-name" name="name" value="{{ old('name') }}" required autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                    @error('name')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-email">{{ __('booking.email') }} <span class="et-required">*</span></label>
                                    <input class="et-input easytire-input" id="booking-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                    @error('email')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field">
                                    <label class="et-label" for="booking-phone">{{ __('booking.phone') }} <span class="et-required">*</span></label>
                                    <input class="et-input easytire-input" id="booking-phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                    @error('phone')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="et-field et-field--full">
                                    <label class="et-label" for="booking-note">{{ __('project.request.problem') }} @if($requiresProblem)<span class="et-required">*</span>@endif</label>
                                    <textarea class="et-textarea easytire-input" id="booking-note" name="note" placeholder="{{ __('project.request.problem_placeholder') }}" @required($requiresProblem) aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                                    @error('note')<p class="et-form-error">{{ $message }}</p>@enderror
                                </div>
                                @if ($isOilService || $serviceSlug === 'lyspaerer-viskerblad')
                                    <fieldset class="et-field et-field--full">
                                        <legend class="et-label">{{ __('project.request.additional_services') }}</legend>
                                        <div class="et-radio-grid" style="margin-top:.6rem">
                                            @foreach (($isOilService ? ['air_filter', 'cabin_filter', 'wiper_blades'] : ['wiper_blades']) as $additionalService)
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

                            <div class="et-alert et-alert--info" style="margin-top:1rem"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('booking.intro') }}</div></div>
                            <label class="et-check" style="margin-top:1.2rem">
                                <input required name="consent" type="checkbox" value="1" @checked(old('consent'))>
                                <span>{{ __('booking.consent') }}</span>
                            </label>
                            @error('consent')<p class="et-form-error">{{ $message }}</p>@enderror
                            <button class="et-btn et-btn--primary" type="submit" style="margin-top:1.2rem" data-testid="generic-submit">{{ __('pro.booking.submit') }}</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </section>
</x-layouts.storefront>
