@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $legalPages = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('content_pages')) {
            $legalPages = app(\App\Modules\Content\ReadModel\PublishedContentPageReadService::class)
                ->publishedSlugs(['personvern', 'kjopsvilkar']);
        }
    } catch (\Throwable) {
        $legalPages = [];
    }
    $vatPercent = (int) config('payments.dintero.vat_percent', 25);
    $vatMinor = $vatPercent > 0 ? (int) round($cart->total->minor * $vatPercent / (100 + $vatPercent)) : 0;
    $hasServices = $cart->serviceLines !== [];
@endphp

<x-layouts.storefront :title="__('commerce.checkout')" active-section="cart">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span><a href="{{ route($prefix.'cart.index') }}">{{ __('commerce.cart') }}</a></span><span>{{ __('commerce.checkout') }}</span></div>
                <p class="et-eyebrow">{{ __('pro.nav.store') }}</p>
                <h1 class="et-h1">{{ __('commerce.checkout') }}</h1>
                <p class="et-lead">{{ __('commerce.pickup') }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact">
        <div class="et-container et-cart-layout">
            <form method="post" class="et-booking-card" data-testid="checkout-form">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">

                <div class="et-booking-card__head">
                    <p class="et-caption">EasyTire</p>
                    <h2 class="et-h3">{{ __('commerce.checkout') }}</h2>
                </div>

                <div class="et-booking-card__body">
                    @if (is_array($confirmedFitment ?? null))
                        <div class="et-alert et-alert--info et-checkout-form-alert" data-testid="checkout-confirmed-fitment"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('commerce.confirmed_fitment', ['size' => $confirmedFitment['width'].'/'.$confirmedFitment['aspect'].' R'.$confirmedFitment['rim']]) }}</div></div>
                    @endif
                    @if($errors->any())
                        <div class="et-alert et-alert--error et-checkout-form-alert" role="alert" data-testid="request-errors"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $errors->first() }}</div></div>
                    @endif
                    @error('payment')<div class="et-alert et-alert--error et-checkout-form-alert" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $message }}</div></div>@enderror

                    <fieldset class="et-fieldset">
                        <legend class="sr-only">{{ __('commerce.checkout') }}</legend>
                        <div class="et-form-grid">
                            <div class="et-field">
                                <label class="et-label" for="checkout-name">{{ __('commerce.name') }} <span class="et-required">*</span></label>
                                <input class="et-input easytire-input" id="checkout-name" data-testid="checkout-name" name="name" type="text" autocomplete="name" required value="{{ old('name') }}" aria-describedby="checkout-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                @error('name')<p class="et-form-error" id="checkout-name-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field">
                                <label class="et-label" for="checkout-email">{{ __('commerce.email') }} <span class="et-required">*</span></label>
                                <input class="et-input easytire-input" id="checkout-email" data-testid="checkout-email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" aria-describedby="checkout-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                @error('email')<p class="et-form-error" id="checkout-email-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field">
                                <label class="et-label" for="checkout-phone">{{ __('commerce.phone') }} <span class="et-required">*</span></label>
                                <input class="et-input easytire-input" id="checkout-phone" data-testid="checkout-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="24" pattern="\+?[0-9][0-9 ()-]{7,23}" required value="{{ old('phone') }}" aria-describedby="checkout-phone-hint @error('phone') checkout-phone-error @enderror" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                <p class="et-help" id="checkout-phone-hint">{{ __('phone_validation.hint') }}</p>
                                @error('phone')<p class="et-form-error" id="checkout-phone-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field">
                                <label class="et-label" for="checkout-registration">{{ __('commerce.registration') }}</label>
                                <input class="et-input easytire-input" id="checkout-registration" name="registration" type="text" autocomplete="off" value="{{ old('registration', is_array($confirmedFitment ?? null) ? $confirmedFitment['registration'] : '') }}" aria-describedby="checkout-registration-error" aria-invalid="{{ $errors->has('registration') ? 'true' : 'false' }}">
                                @error('registration')<p class="et-form-error" id="checkout-registration-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field et-field--full">
                                <label class="et-label" for="checkout-note">{{ __('commerce.note') }}</label>
                                <textarea class="et-textarea easytire-input" id="checkout-note" name="note" aria-describedby="checkout-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                                @error('note')<p class="et-form-error" id="checkout-note-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="et-fieldset et-checkout-consent-section">
                        <legend class="sr-only">{{ __('commerce.terms') }}</legend>
                        <div class="et-stack">
                            <label class="et-check" data-testid="checkout-fitment-confirmation">
                                <input required type="checkbox" name="fitment_confirmation" value="1" @checked(old('fitment_confirmation')) aria-describedby="checkout-fitment-confirmation-error" aria-invalid="{{ $errors->has('fitment_confirmation') ? 'true' : 'false' }}">
                                <span>{{ __('commerce.fitment_confirmation') }}</span>
                            </label>
                            @error('fitment_confirmation')<p class="et-form-error" id="checkout-fitment-confirmation-error">{{ $message }}</p>@enderror

                            @if($hasServices)
                                <label class="et-check" data-testid="checkout-early-service-start">
                                    <input required type="checkbox" name="early_service_start" value="1" @checked(old('early_service_start')) aria-describedby="checkout-early-service-start-error" aria-invalid="{{ $errors->has('early_service_start') ? 'true' : 'false' }}">
                                    <span>{{ __('commerce.early_service_start') }}</span>
                                </label>
                                @error('early_service_start')<p class="et-form-error" id="checkout-early-service-start-error">{{ $message }}</p>@enderror
                            @endif

                            <label class="et-check" data-testid="checkout-privacy">
                                <input required type="checkbox" name="consent" value="1" @checked(old('consent')) aria-label="{{ __('commerce.consent') }}" aria-describedby="checkout-consent-error" aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}">
                                @if (isset($legalPages['personvern']))
                                    <span>{{ __('commerce.privacy_acknowledgement') }} <a class="et-btn--link" href="{{ route($prefix.'content.show', 'personvern') }}" target="_blank" rel="noopener">{{ __('legal.privacy') }}</a>.</span>
                                @else
                                    <span>{{ __('commerce.consent') }}</span>
                                @endif
                            </label>
                            @error('consent')<p class="et-form-error" id="checkout-consent-error">{{ $message }}</p>@enderror

                            <label class="et-check" data-testid="checkout-terms">
                                <input required type="checkbox" name="terms" value="1" @checked(old('terms')) aria-label="{{ __('commerce.terms') }}" aria-describedby="checkout-terms-error" aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}">
                                @if (isset($legalPages['kjopsvilkar']))
                                    <span>{{ __('legal.terms_prefix') }} <a class="et-btn--link" href="{{ route($prefix.'content.show', 'kjopsvilkar') }}" target="_blank" rel="noopener">{{ __('legal.terms') }}</a> ({{ \App\Modules\Orders\LegalDocumentVersions::TERMS }}).</span>
                                @else
                                    <span>{{ __('commerce.terms') }}</span>
                                @endif
                            </label>
                            @error('terms')<p class="et-form-error" id="checkout-terms-error">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>

                    @if(config('payments.enabled'))
                        <div class="et-alert et-alert--info et-checkout-payment-notice"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('commerce.payment_authorization_notice') }}</div></div>
                    @endif
                    <p class="et-help et-checkout-obligation">{{ __('commerce.payment_obligation_help') }}</p>
                    <button class="et-btn et-btn--primary et-checkout-submit" type="submit" data-testid="checkout-submit">{{ __('commerce.order_with_payment_obligation') }}</button>
                </div>
            </form>

            <aside class="et-order-summary" aria-label="{{ __('commerce.cart_total') }}">
                <p class="et-eyebrow">{{ __('commerce.cart_total') }}</p>
                <div class="et-stack et-order-summary__lines">
                    @forelse($cart->lines as $line)
                        @php($lineTitle = filled($line->item->modelName) ? trim($line->item->brand.' '.$line->item->modelName) : $line->item->title)
                        <div class="et-order-summary__row"><span>{{ $lineTitle }} × {{ $line->quantity }}</span><strong>{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</strong></div>
                    @empty
                        <p class="et-caption">{{ __('commerce.empty') }}</p>
                    @endforelse
                    @foreach($cart->serviceLines as $line)
                        <div class="et-order-summary__row" data-testid="checkout-service-item"><span>{{ $line->title(app()->getLocale()) }} × {{ $line->quantity }}</span><strong>{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</strong></div>
                    @endforeach
                </div>
                <dl class="et-order-summary__breakdown">
                    <div class="et-order-summary__row"><dt>{{ __('commerce.products_total') }}</dt><dd><strong>{{ number_format($cart->productsTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->productsTotal()->currency }}</strong></dd></div>
                    @if ($cart->servicesTotal()->minor > 0)<div class="et-order-summary__row"><dt>{{ __('commerce.services_total') }}</dt><dd><strong>{{ number_format($cart->servicesTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->servicesTotal()->currency }}</strong></dd></div>@endif
                    <div class="et-order-summary__row" data-testid="checkout-vat-summary"><dt>{{ __('commerce.vat_included') }}</dt><dd><strong>{{ number_format($vatMinor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</strong></dd></div>
                    <div class="et-order-summary__row"><dt>{{ __('commerce.mandatory_fees') }}</dt><dd><strong>{{ __('commerce.no_mandatory_fees') }}</strong></dd></div>
                </dl>
                <div class="et-order-summary__total"><span>{{ __('commerce.cart_total') }}</span><span>{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</span></div>
                <p class="et-help et-order-summary__note">{{ __('commerce.fulfilment_summary') }}</p>
                <div class="et-alert et-alert--info et-order-summary__note"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ config('payments.enabled') ? __('commerce.payment_summary_notice') : __('commerce.pending') }}</div></div>
                <p class="et-help et-order-summary__note" data-testid="checkout-merchant-identity">{{ __('commerce.merchant_identity') }}</p>
            </aside>
        </div>
    </section>
</x-layouts.storefront>
