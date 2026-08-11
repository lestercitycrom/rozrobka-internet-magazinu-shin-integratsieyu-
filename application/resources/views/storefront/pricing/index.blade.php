@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')

<x-layouts.storefront :title="__('public_pricing.title')" :description="__('public_pricing.description')" :canonical="route('storefront.pricing.index')" active-section="services">
    <section class="et-page-hero et-page-hero--light">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ __('public_pricing.heading') }}</span></div>
                <p class="et-eyebrow">{{ __('public_pricing.kicker') }}</p>
                <h1 class="et-h1">{{ __('public_pricing.heading') }}</h1>
                <p class="et-lead">{{ __('public_pricing.intro') }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact">
        <div class="et-container">
            @if ($addons->isNotEmpty())
                <div class="et-card" data-testid="public-price-list">
                    <div class="et-card__body">
                        <div class="et-stack">
                            @foreach ($addons as $addon)
                                <div class="et-card et-card--flat" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem">
                                    <div><strong>{{ $addon->title(app()->getLocale()) }}</strong><p class="et-caption">{{ __('public_pricing.billing_'.$addon->billing_mode->value) }}</p></div>
                                    <strong>{{ number_format($addon->price_minor / 100, 2, ',', ' ') }} NOK</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="et-empty-state" data-testid="public-prices-pending">
                    <div><div class="et-empty-state__icon" aria-hidden="true">i</div><h2 class="et-h3" style="margin-top:1rem">{{ __('public_pricing.pending_title') }}</h2><p class="et-copy">{{ __('public_pricing.pending_text') }}</p></div>
                    <a class="et-btn et-btn--primary" href="{{ route($prefix.'services.index') }}">{{ __('public_pricing.cta') }}</a>
                </div>
            @endif
            <p class="et-help" style="margin-top:1rem">{{ __('public_pricing.confirmation') }}</p>
        </div>
    </section>
</x-layouts.storefront>
