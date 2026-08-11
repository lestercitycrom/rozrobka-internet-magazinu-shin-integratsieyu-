@php
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $isStoreLanding = request()->routeIs($routePrefix.'store.*');
    $pageTitle = $isStoreLanding ? __('seo_store.title') : __('fitment.title');
    $pageDescription = $isStoreLanding ? __('seo_store.description') : null;
    $pageHeading = $isStoreLanding ? __('seo_store.heading') : __('fitment.title');
    $pageLead = $isStoreLanding ? __('seo_store.lead') : __('fitment.notice');
    $canonical = $isStoreLanding ? route('storefront.store.index') : null;
@endphp

<x-layouts.storefront :title="$pageTitle" :description="$pageDescription" :canonical="$canonical" active-section="store">
    <section class="et-page-hero">
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

    <section class="et-section et-section--compact">
        <div class="et-container et-split">
            <div class="et-card">
                <div class="et-card__body">
                    <p class="et-eyebrow">{{ __('pro.fitment.panel_title') }}</p>
                    <h2 class="et-h2">{{ __('fitment.registration') }}</h2>
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
                        <div class="et-stack" style="margin-top:1rem">
                            @foreach ($fitment->tyres as $tyre)
                                <a class="et-card et-card--interactive" href="{{ route($routePrefix.'tires.index', ['width' => $tyre['width'], 'aspect' => $tyre['aspect'], 'rim' => $tyre['rim']]) }}">
                                    <div class="et-card__body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
                                        <strong>{{ $tyre['width'] }}/{{ $tyre['aspect'] }} R{{ $tyre['rim'] }}</strong><span aria-hidden="true">→</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
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
        </div>
    </section>
</x-layouts.storefront>
