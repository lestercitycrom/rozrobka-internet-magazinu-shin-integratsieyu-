@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
@endphp

<x-layouts.storefront :title="__('public_pricing.title')" :description="__('public_pricing.description')" :canonical="route('storefront.pricing.index')" active-section="pricing">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
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
            @if ($prices->isNotEmpty())
                <div class="et-public-prices" data-testid="public-price-list">
                    @foreach ($prices as $categoryPrices)
                        @php
                            $category = $categoryPrices->first();
                            $categoryAction = match ((string) $category->category_code) {
                                'workshop' => [route($prefix.'requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop']), __('services.workshop_intent_cta')],
                                'mobile' => [route($prefix.'requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile']), __('services.mobile_intent_cta')],
                                'hotel' => [route($prefix.'requests.create', ['slug' => 'dekkhotell']), __('services.request_cta')],
                                default => [route($prefix.'services.index'), __('public_pricing.cta')],
                            };
                        @endphp
                        <article class="et-public-price-group" id="price-category-{{ \Illuminate\Support\Str::slug($category->category_code) }}">
                            <header class="et-public-price-group__head">
                                <p class="et-eyebrow">{{ __('public_pricing.category_kicker') }}</p>
                                <h2 class="et-h3">{{ $category->localized('category_title', app()->getLocale()) }}</h2>
                            </header>
                            <div class="et-public-price-group__rows">
                                @foreach ($categoryPrices as $price)
                                    <div class="et-public-price-row" id="price-{{ \Illuminate\Support\Str::slug($price->code) }}" data-testid="public-price-row">
                                        <div>
                                            <strong>{{ $price->localized('title', app()->getLocale()) }}</strong>
                                            @if ($description = $price->localized('description', app()->getLocale()))
                                                <p>{{ $description }}</p>
                                            @endif
                                            <a class="et-action-link et-action-link--small" href="{{ $categoryAction[0] }}"><span class="et-action-link__label">{{ $categoryAction[1] }}</span><x-icon name="arrow" :size="16" /></a>
                                        </div>
                                        <strong class="et-public-price-row__amount"><x-storefront-money :minor="$price->price_minor" :currency="$price->currency" :decimals="0" /></strong>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="et-empty-state" data-testid="public-prices-pending">
                    <div><div class="et-empty-state__icon" aria-hidden="true">i</div><h2 class="et-h3 et-empty-state__heading">{{ __('public_pricing.pending_title') }}</h2><p class="et-copy">{{ __('public_pricing.pending_text') }}</p></div>
                    <a class="et-btn et-btn--primary" href="{{ route($prefix.'services.index') }}">{{ __('public_pricing.cta') }}</a>
                </div>
            @endif
            <div class="et-alert et-alert--info et-pricing-confirmation"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('public_pricing.confirmation') }}</div></div>
        </div>
    </section>
</x-layouts.storefront>
