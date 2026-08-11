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
                    @if($errors->any())
                        <div class="et-alert et-alert--error" role="alert" style="margin-bottom:1.2rem"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $errors->first() }}</div></div>
                    @endif

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
                                <input class="et-input easytire-input" id="checkout-phone" data-testid="checkout-phone" name="phone" type="tel" autocomplete="tel" required value="{{ old('phone') }}" aria-describedby="checkout-phone-error" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                                @error('phone')<p class="et-form-error" id="checkout-phone-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field">
                                <label class="et-label" for="checkout-registration">{{ __('commerce.registration') }}</label>
                                <input class="et-input easytire-input" id="checkout-registration" name="registration" type="text" autocomplete="off" value="{{ old('registration') }}" aria-describedby="checkout-registration-error" aria-invalid="{{ $errors->has('registration') ? 'true' : 'false' }}">
                                @error('registration')<p class="et-form-error" id="checkout-registration-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="et-field et-field--full">
                                <label class="et-label" for="checkout-note">{{ __('commerce.note') }}</label>
                                <textarea class="et-textarea easytire-input" id="checkout-note" name="note" aria-describedby="checkout-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ old('note') }}</textarea>
                                @error('note')<p class="et-form-error" id="checkout-note-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="et-fieldset" style="margin-top:1.5rem;border-top:1px solid var(--et-line);padding-top:1.3rem">
                        <legend class="sr-only">{{ __('commerce.consent') }}</legend>
                        <div class="et-stack">
                            <label class="et-check">
                                <input required type="checkbox" name="consent" value="1" @checked(old('consent')) aria-label="{{ __('commerce.consent') }}">
                                @if (isset($legalPages['personvern']))
                                    <span>{{ __('legal.consent_prefix') }} <a class="et-btn--link" href="{{ route($prefix.'content.show', 'personvern') }}">{{ __('legal.privacy') }}</a>.</span>
                                @else
                                    <span>{{ __('commerce.consent') }}</span>
                                @endif
                            </label>
                            @error('consent')<p class="et-form-error">{{ $message }}</p>@enderror

                            <label class="et-check">
                                <input required type="checkbox" name="terms" value="1" @checked(old('terms')) aria-label="{{ __('commerce.terms') }}">
                                @if (isset($legalPages['kjopsvilkar']))
                                    <span>{{ __('legal.terms_prefix') }} <a class="et-btn--link" href="{{ route($prefix.'content.show', 'kjopsvilkar') }}">{{ __('legal.terms') }}</a>.</span>
                                @else
                                    <span>{{ __('commerce.terms') }}</span>
                                @endif
                            </label>
                            @error('terms')<p class="et-form-error">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>

                    <button class="et-btn et-btn--primary" type="submit" style="margin-top:1.5rem" data-testid="checkout-submit">{{ __('commerce.submit') }}</button>
                </div>
            </form>

            <aside class="et-order-summary">
                <p class="et-eyebrow">{{ __('commerce.cart_total') }}</p>
                <div class="et-stack" style="margin-top:1rem">
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
                <dl style="margin-top:1rem;border-top:1px solid var(--et-line);padding-top:.5rem">
                    <div class="et-order-summary__row"><dt>{{ __('commerce.products_total') }}</dt><dd><strong>{{ number_format($cart->productsTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->productsTotal()->currency }}</strong></dd></div>
                    @if ($cart->servicesTotal()->minor > 0)<div class="et-order-summary__row"><dt>{{ __('commerce.services_total') }}</dt><dd><strong>{{ number_format($cart->servicesTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->servicesTotal()->currency }}</strong></dd></div>@endif
                </dl>
                <div class="et-order-summary__total"><span>{{ __('commerce.cart_total') }}</span><span>{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</span></div>
                <div class="et-alert et-alert--info" style="margin-top:1rem"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('commerce.pending') }}</div></div>
            </aside>
        </div>
    </section>
</x-layouts.storefront>
