@php
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $storefrontLocale = app()->getLocale() === 'ru' ? 'ru' : 'nb';
    $storefrontSettings = $settings ?? null;
    $storefrontValue = static function (string $field, string $fallback) use ($storefrontSettings): string {
        $value = $storefrontSettings?->getAttribute($field);

        return filled($value) ? (string) $value : $fallback;
    };
    $isStoreLanding = request()->routeIs($routePrefix.'store.*');
    $hasFitment = isset($fitment) && $fitment;
    $pageTitle = $isStoreLanding ? __('seo_store.title') : __('fitment.title');
    $pageDescription = $isStoreLanding ? __('seo_store.description') : null;
    $pageHeading = $isStoreLanding ? $storefrontValue('store_heading_'.$storefrontLocale, __('seo_store.heading')) : __('fitment.title');
    $pageLead = $isStoreLanding ? $storefrontValue('store_lead_'.$storefrontLocale, __('seo_store.lead')) : __('fitment.notice');
    $canonical = $isStoreLanding ? route('storefront.store.index') : null;
@endphp

<x-layouts.storefront :title="$pageTitle" :description="$pageDescription" :canonical="$canonical" active-section="store">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($routePrefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $pageHeading }}</span></div>
                <p class="et-eyebrow">{{ __('pro.fitment.kicker') }}</p>
                <h1 class="et-h1">{{ $pageHeading }}</h1>
                <p class="et-lead">{{ $pageLead }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact {{ $isStoreLanding && ! $hasFitment ? 'et-store-finder-section' : '' }}">
        <div class="et-container {{ $isStoreLanding && ! $hasFitment ? 'et-store-finder-wrap' : 'et-split' }}">
            @if ($isStoreLanding && ! $hasFitment)
                @if (request()->isMethod('post'))
                    <div class="et-alert et-alert--info et-store-lookup-feedback" role="status">
                        <span class="et-alert__icon" aria-hidden="true">i</span>
                        <div>{{ __('fitment.lookup_not_found') }}</div>
                    </div>
                @endif
                @include('storefront._quick-finder', ['idPrefix' => 'store-finder', 'showStoreLink' => false])
            @else
            <div class="et-card">
                <div class="et-card__body">
                    <p class="et-eyebrow">{{ __('pro.fitment.panel_title') }}</p>
                    <h2 class="et-h2">{{ __('fitment.registration_heading_before_break') }}<wbr>{{ __('fitment.registration_heading_after_break') }}</h2>
                    <p class="et-copy">{{ __('fitment.registration_help') }}</p>

                    @if ($errors->any())
                        <div class="et-alert et-alert--error et-fitment-alert" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $errors->first() }}</div></div>
                    @endif

                    <form class="et-fitment-lookup" method="POST" action="{{ url()->current() }}">
                        @csrf
                        <div class="et-field">
                            <label class="et-label" for="registration">{{ __('fitment.registration') }}</label>
                            <div class="et-reg-input">
                                <input
                                    class="et-input"
                                    id="registration"
                                    name="registration"
                                    type="text"
                                    value="{{ old('registration') }}"
                                    required
                                    maxlength="16"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    aria-describedby="registration-help{{ $errors->has('registration') ? ' registration-error' : '' }}"
                                    aria-invalid="{{ $errors->has('registration') ? 'true' : 'false' }}"
                                >
                            </div>
                            <p class="et-help" id="registration-help">{{ __('pro.fitment.privacy') }}</p>
                            @error('registration')<p class="et-form-error" id="registration-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button class="et-btn et-btn--primary et-fitment-submit" type="submit">{{ __('fitment.submit') }}</button>
                    </form>
                </div>
            </div>

            @if (isset($fitment) && $fitment)
                <aside class="et-card" aria-labelledby="fitment-result-title">
                    <div class="et-card__body">
                        <p class="et-eyebrow">{{ __('pro.fitment.result_kicker') }}</p>
                        <h2 class="et-h2" id="fitment-result-title">{{ $fitment->make }} {{ $fitment->model }}</h2>
                        <p class="et-copy">{{ __('fitment.results_notice') }}</p>
                        <h3 class="et-h3 et-fitment-result-heading">{{ __('pro.fitment.result_title') }}</h3>
                        <form method="post" action="{{ route($routePrefix.'fitment.confirm') }}" class="et-fitment-choice">
                            @csrf
                            <fieldset class="et-fieldset">
                                <legend class="et-label">{{ __('fitment.intent_legend') }}</legend>
                                <div class="et-radio-grid et-fitment-intents">
                                    @foreach (['tires', 'wheels', 'packages', 'tpms'] as $intent)
                                        <label class="et-radio-card">
                                            <input name="intent" value="{{ $intent }}" type="radio" required @checked(old('intent', 'tires') === $intent)>
                                            <span><strong>{{ __('fitment.intent_'.$intent) }}</strong><small>{{ __('fitment.intent_'.$intent.'_help') }}</small></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <p class="et-caption et-fitment-size-prompt">{{ __('fitment.choose_size') }}</p>
                            <div class="et-stack et-fitment-size-list">
                                @foreach ($fitment->tyres as $tyre)
                                    <button class="et-card et-card--interactive et-fitment-size-option" data-testid="fitment-confirm-size" name="size" value="{{ $tyre['width'] }}|{{ $tyre['aspect'] }}|{{ $tyre['rim'] }}" type="submit">
                                        <span class="et-card__body et-fitment-size-option__body">
                                            <span><strong>{{ $tyre['width'] }}/{{ $tyre['aspect'] }} R{{ $tyre['rim'] }}</strong><small class="et-fitment-size-option__help">{{ __('fitment.confirm_size') }}</small></span><span aria-hidden="true">→</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </form>
                        @if ($fitment->wheels !== [])
                            <h3 class="et-h3 et-fitment-result-heading">{{ __('fitment.original_wheels') }}</h3>
                            <p class="et-copy">{{ __('fitment.wheel_notice') }}</p>
                            <ul class="et-stack et-fitment-wheel-list" data-testid="fitment-wheel-sizes">
                                @foreach ($fitment->wheels as $wheel)
                                    <li class="et-card"><div class="et-card__body"><strong>{{ $wheel['dimension'] }}</strong>@if($wheel['offset']) <span>· {{ __('storefront.offset') }} {{ $wheel['offset'] }}</span>@endif</div></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </aside>
            @else
                <aside class="et-media-card" aria-labelledby="fitment-fallback-title">
                    <img src="/brand/workshop.webp" width="1500" height="1000" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}">
                    <div class="et-media-card__content">
                        <p class="et-eyebrow et-eyebrow--light">{{ __('pro.fitment.manual_kicker') }}</p>
                        <h2 class="et-h2" id="fitment-fallback-title">{{ __('fitment.fallback') }}</h2>
                        <p>{{ __('fitment.manual_help') }}</p>
                        <a class="et-btn et-btn--ghost" href="{{ route($routePrefix.'tires.index') }}">{{ __('fitment.manual') }}</a>
                    </div>
                </aside>
            @endif
            @endif
        </div>
    </section>

    @if ($isStoreLanding)
        <section class="et-section et-section--ink" aria-labelledby="store-process-title">
            <div class="et-container">
                <p class="et-eyebrow et-eyebrow--light">{{ __('seo_store.advantages_kicker') }}</p>
                <h2 class="et-h2 et-heading--on-dark" id="store-process-title">{{ __('seo_store.advantages_title') }}</h2>
                <div class="et-grid-3 et-store-card-grid">
                    @foreach ([1, 2, 3] as $step)
                        <article class="et-step-card"><span class="et-step-index">0{{ $step }}</span><h3>{{ $storefrontValue('store_benefit_'.$step.'_title_'.$storefrontLocale, __('seo_store.advantage_'.$step.'_title')) }}</h3><p>{{ $storefrontValue('store_benefit_'.$step.'_text_'.$storefrontLocale, __('seo_store.advantage_'.$step.'_text')) }}</p></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="et-section" aria-labelledby="store-services-title">
            <div class="et-container et-split">
                <div>
                    <p class="et-eyebrow">{{ __('seo_store.services_kicker') }}</p>
                    <h2 class="et-h2" id="store-services-title">{{ __('seo_store.services_title') }}</h2>
                    <p class="et-copy">{{ __('seo_store.services_text') }}</p>
                    <div class="et-store-service-actions">
                        <a class="et-btn et-btn--primary" href="{{ route($routePrefix.'services.show', ['slug' => 'mobilt-dekkskift']) }}">{{ __('seo_store.change_cta') }}</a>
                        <a class="et-btn et-btn--outline" href="{{ route($routePrefix.'services.show', ['slug' => 'dekkhotell']) }}">{{ __('seo_store.hotel_cta') }}</a>
                    </div>
                </div>
                <figure class="et-media-card"><img src="/brand/workshop.webp" width="1500" height="1000" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}"></figure>
            </div>
            @if (! empty($brands))
                <div class="et-container et-store-brands">
                    <p class="et-caption">{{ __('seo_store.brands_label') }}</p>
                    <div class="et-brand-strip" aria-label="{{ __('seo_store.brands_label') }}">
                        @foreach ($brands as $brand)<span>{{ $brand }}</span>@endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif
</x-layouts.storefront>
