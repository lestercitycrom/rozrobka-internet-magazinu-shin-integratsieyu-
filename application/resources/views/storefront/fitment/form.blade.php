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
    <section class="et-page-hero {{ $isStoreLanding ? 'et-page-hero--store' : '' }}">
        <img class="et-page-hero__photo" src="/brand/workshop-alt.webp" width="1400" height="900" alt="{{ __('pro.content.workshop_image_alt') }}">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($routePrefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $pageHeading }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.fitment.kicker') }}</p>
                <h1 class="et-h1">{{ $pageHeading }}</h1>
                <p class="et-lead et-lead--light">{{ $pageLead }}</p>
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
                        <div class="et-alert et-alert--error" role="alert" style="margin-top:1rem"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $errors->first() }}</div></div>
                    @endif

                    <form method="POST" action="{{ url()->current() }}" style="margin-top:1.4rem">
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
                        <button class="et-btn et-btn--primary" type="submit" style="margin-top:1rem">{{ __('fitment.submit') }}</button>
                    </form>
                </div>
            </div>

            @if (isset($fitment) && $fitment)
                <aside class="et-card" aria-labelledby="fitment-result-title">
                    <div class="et-card__body">
                        <p class="et-eyebrow">{{ __('pro.fitment.result_kicker') }}</p>
                        <h2 class="et-h2" id="fitment-result-title">{{ $fitment->make }} {{ $fitment->model }}</h2>
                        <p class="et-copy">{{ __('fitment.results_notice') }}</p>
                        <h3 class="et-h3" style="margin-top:1.5rem">{{ __('pro.fitment.result_title') }}</h3>
                        <form method="post" action="{{ route($routePrefix.'fitment.confirm') }}" class="et-fitment-choice" style="margin-top:1rem">
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
                            <p class="et-caption" style="margin-top:1.25rem">{{ __('fitment.choose_size') }}</p>
                            <div class="et-stack" style="margin-top:.65rem">
                                @foreach ($fitment->tyres as $tyre)
                                    <button class="et-card et-card--interactive" data-testid="fitment-confirm-size" name="size" value="{{ $tyre['width'] }}|{{ $tyre['aspect'] }}|{{ $tyre['rim'] }}" type="submit" style="width:100%;text-align:left">
                                        <span class="et-card__body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
                                            <span><strong>{{ $tyre['width'] }}/{{ $tyre['aspect'] }} R{{ $tyre['rim'] }}</strong><small style="display:block;margin-top:.25rem">{{ __('fitment.confirm_size') }}</small></span><span aria-hidden="true">→</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </form>
                        @if ($fitment->wheels !== [])
                            <h3 class="et-h3" style="margin-top:1.5rem">{{ __('fitment.original_wheels') }}</h3>
                            <p class="et-copy">{{ __('fitment.wheel_notice') }}</p>
                            <ul class="et-stack" data-testid="fitment-wheel-sizes" style="margin-top:1rem">
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
        <section class="et-section" aria-labelledby="store-categories-title">
            <div class="et-container">
                <p class="et-eyebrow">{{ __('seo_store.categories_kicker') }}</p>
                <h2 class="et-h2" id="store-categories-title">{{ __('seo_store.categories_title') }}</h2>
                <p class="et-copy" style="max-width:48rem">{{ __('seo_store.categories_lead') }}</p>
                <div class="et-grid-3" style="margin-top:1.7rem">
                    <a class="et-card et-card--interactive" href="{{ route($routePrefix.'tires.index') }}"><div class="et-card__body"><span class="et-step-index">01</span><h3 class="et-h3">{{ __('seo_store.tires_title') }}</h3><p>{{ __('seo_store.tires_text') }}</p><strong>{{ __('seo_store.explore') }} →</strong></div></a>
                    <a class="et-card et-card--interactive" href="{{ route($routePrefix.'wheels.index') }}"><div class="et-card__body"><span class="et-step-index">02</span><h3 class="et-h3">{{ __('seo_store.wheels_title') }}</h3><p>{{ __('seo_store.wheels_text') }}</p><strong>{{ __('seo_store.explore') }} →</strong></div></a>
                    <a class="et-card et-card--interactive" href="{{ route($routePrefix.'requests.create', ['slug' => 'omlegging-balansering']) }}"><div class="et-card__body"><span class="et-step-index">03</span><h3 class="et-h3">{{ __('seo_store.packages_title') }}</h3><p>{{ __('seo_store.packages_text') }}</p><strong>{{ __('seo_store.ask_us') }} →</strong></div></a>
                </div>
            </div>
        </section>

        <section class="et-section et-section--ink" aria-labelledby="store-process-title">
            <div class="et-container">
                <p class="et-eyebrow et-eyebrow--light">{{ __('seo_store.advantages_kicker') }}</p>
                <h2 class="et-h2" id="store-process-title" style="color:#fff">{{ __('seo_store.advantages_title') }}</h2>
                <div class="et-grid-3" style="margin-top:1.7rem">
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
                    <div style="display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1.4rem">
                        <a class="et-btn et-btn--primary" href="{{ route($routePrefix.'services.show', ['slug' => 'mobilt-dekkskift']) }}">{{ __('seo_store.change_cta') }}</a>
                        <a class="et-btn et-btn--outline" href="{{ route($routePrefix.'services.show', ['slug' => 'dekkhotell']) }}">{{ __('seo_store.hotel_cta') }}</a>
                    </div>
                </div>
                <figure class="et-media-card"><img src="/brand/workshop.webp" width="1500" height="1000" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}"></figure>
            </div>
            @if (! empty($brands))
                <div class="et-container" style="margin-top:2.5rem">
                    <p class="et-caption">{{ __('seo_store.brands_label') }}</p>
                    <div class="et-brand-strip" aria-label="{{ __('seo_store.brands_label') }}">
                        @foreach ($brands as $brand)<span>{{ $brand }}</span>@endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif
</x-layouts.storefront>
