@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $heroUrl = $service->hero['url'] ?? '/brand/workshop-alt.webp';
    $heroAlt = $service->hero['alt'] ?? __('pro.content.workshop_image_alt');
    $contact = app(\App\Modules\Content\ReadModel\StorefrontSettingsReadService::class)->contact();
    $phoneHref = $contact['phone_href'];
    $phoneDisplay = $contact['phone_display'];
    $isPhoneOnlyService = $service->slug === 'dekkreparasjon';
    $hasSeasonalIntentChoice = $service->slug === 'mobilt-dekkskift';
    $requestRouteName = $prefix.'requests.create';
    $mobileRequestHref = route($requestRouteName, ['slug' => $service->slug, 'fulfilment' => 'mobile']);
    $workshopRequestHref = route($requestRouteName, ['slug' => $service->slug, 'fulfilment' => 'workshop']);
    $primaryCtaHref = $isPhoneOnlyService
        ? 'tel:'.$phoneHref
        : route($requestRouteName, $service->slug);
    $primaryCtaLabel = $isPhoneOnlyService ? __('services.call_cta') : __('services.request_cta');
    $primaryCtaTestId = $isPhoneOnlyService ? 'service-hero-call' : 'service-hero-request';
    $serviceSchema = [
        '@context' => 'https://schema.org', '@type' => 'Service',
        'name' => $service->title, 'description' => $service->seoDescription ?: $service->excerpt,
        'url' => $canonicalUrl ?? url()->current(),
        'provider' => ['@id' => url('/').'#business'],
        'areaServed' => ['@type' => 'City', 'name' => 'Tromsø'],
    ];
@endphp

<x-layouts.storefront :title="$service->seoTitle ?? ('EasyTire | '.$service->title)" :description="$service->seoDescription" :canonical="$canonicalUrl ?? null" :image="$heroUrl" :structured-data="[$serviceSchema]" active-section="services">
    <article>
        <section class="et-service-hero">
            <div class="et-service-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span><a href="{{ route($prefix.'services.index') }}">{{ __('pro.nav.services') }}</a></span><span>{{ $service->title }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.services.kicker') }}</p>
                <h1 class="et-h1">{{ $service->title }}</h1>
                @if ($service->excerpt)<p class="et-lead et-lead--light">{{ $service->excerpt }}</p>@endif
                <div class="et-page-actions" style="margin-top:1.5rem">
                    @if ($hasSeasonalIntentChoice)
                        <a class="et-btn et-btn--primary" href="{{ $mobileRequestHref }}" data-testid="service-hero-mobile">{{ __('services.mobile_intent_cta') }}</a>
                        <a class="et-btn et-btn--ghost" href="{{ $workshopRequestHref }}" data-testid="service-hero-workshop">{{ __('services.workshop_intent_cta') }}</a>
                    @else
                        <a class="et-btn et-btn--primary" href="{{ $primaryCtaHref }}" data-testid="{{ $primaryCtaTestId }}">{{ $primaryCtaLabel }}</a>
                        @unless ($isPhoneOnlyService)<a class="et-btn et-btn--ghost" href="tel:{{ $phoneHref }}">{{ __('services.call_cta') }} {{ $phoneDisplay }}</a>@endunless
                    @endif
                </div>
            </div>
            <div class="et-service-hero__media">
                <img data-testid="page-hero" src="{{ $heroUrl }}" alt="{{ $heroAlt }}" @if($service->hero) loading="eager" @else loading="lazy" @endif>
            </div>
        </section>

        <div class="et-service-anchor">
            <div class="et-container et-service-anchor__inner">
                <nav aria-label="{{ $service->title }}">
                    <a href="#service-details">{{ __('storefront.product_information') }}</a>
                    <a href="#service-request">{{ __('pro.services.request_kicker') }}</a>
                </nav>
                @if ($hasSeasonalIntentChoice)
                    <div class="et-page-actions">
                        <a class="et-btn et-btn--primary et-btn--small" href="{{ $mobileRequestHref }}" data-testid="service-anchor-mobile">{{ __('services.mobile_intent_cta') }}</a>
                        <a class="et-btn et-btn--ghost et-btn--small" href="{{ $workshopRequestHref }}" data-testid="service-anchor-workshop">{{ __('services.workshop_intent_cta') }}</a>
                    </div>
                @else
                    <a class="et-btn et-btn--primary et-btn--small" href="{{ $primaryCtaHref }}" data-testid="{{ $isPhoneOnlyService ? 'service-anchor-call' : 'service-anchor-request' }}">{{ $primaryCtaLabel }}</a>
                @endif
            </div>
        </div>

        <section class="et-section et-section--white" id="service-details">
            <div class="et-container et-split">
                <div>
                    <p class="et-eyebrow">{{ __('pro.services.kicker') }}</p>
                    <h2 class="et-h2">{{ $service->title }}</h2>
                    @if ($service->body)
                        <div class="et-richtext" style="margin-top:1.5rem">{!! \Illuminate\Support\Str::markdown($service->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @elseif ($service->excerpt)
                        <p class="et-lead">{{ $service->excerpt }}</p>
                    @endif
                    @if (($servicePrices ?? collect())->isNotEmpty())
                        <section class="et-price-panel" style="margin-top:2rem" aria-labelledby="service-price-heading" data-testid="service-price-list">
                            <h2 class="et-h3" id="service-price-heading">{{ __('services.current_prices') }}</h2>
                            @foreach ($servicePrices as $categoryPrices)
                                @php($category = $categoryPrices->first())
                                <h3 style="margin:1.2rem 0 .4rem">{{ $category->localized('category_title', app()->getLocale()) }}</h3>
                                @foreach ($categoryPrices as $price)
                                    <div class="et-public-price-row">
                                        <div><strong>{{ $price->localized('title', app()->getLocale()) }}</strong>@if($description = $price->localized('description', app()->getLocale()))<p>{{ $description }}</p>@endif</div>
                                        <strong class="et-public-price-row__amount">{{ number_format($price->price_minor / 100, 0, ',', ' ') }} {{ $price->currency }}</strong>
                                    </div>
                                @endforeach
                            @endforeach
                            <a class="et-btn et-btn--link" href="{{ route($prefix.'pricing.index') }}">{{ __('services.all_prices') }}</a>
                        </section>
                    @endif
                </div>
                <aside class="et-sticky-cta" id="service-request">
                    <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                    <h2 class="et-h3" style="margin-top:.6rem">{{ __('pro.services.request_title') }}</h2>
                    <p class="et-copy">{{ __('services.request_note') }}</p>
                    <div class="et-alert et-alert--info" style="margin-block:1rem">
                        <span class="et-alert__icon" aria-hidden="true">i</span>
                        <div>{{ __('booking.intro') }}</div>
                    </div>
                    @if ($hasSeasonalIntentChoice)
                        <a class="et-btn et-btn--primary et-btn--full" href="{{ $mobileRequestHref }}" data-testid="service-aside-mobile">{{ __('services.mobile_intent_cta') }}</a>
                        <a class="et-btn et-btn--ghost et-btn--full" href="{{ $workshopRequestHref }}" data-testid="service-aside-workshop" style="margin-top:.6rem">{{ __('services.workshop_intent_cta') }}</a>
                    @else
                        <a class="et-btn et-btn--primary et-btn--full" href="{{ $primaryCtaHref }}" data-testid="{{ $isPhoneOnlyService ? 'service-aside-call' : 'service-aside-request' }}">{{ $primaryCtaLabel }}</a>
                    @endif
                </aside>
            </div>
        </section>
    </article>
</x-layouts.storefront>
