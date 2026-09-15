@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $heroUrl = $serviceMedia['url'];
    $heroAlt = $serviceMedia['alt'];
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
    $bodySections = $service->body
        ? preg_split('/(?=^##\s+)/m', trim($service->body), -1, PREG_SPLIT_NO_EMPTY)
        : [];
    $introBody = array_shift($bodySections);
    $remainingBody = implode("\n\n", $bodySections);
    $serviceSchema = [
        '@context' => 'https://schema.org', '@type' => 'Service',
        'name' => $service->title, 'description' => $service->seoDescription ?: $service->excerpt,
        'url' => $canonicalUrl ?? url()->current(),
        'provider' => ['@id' => url('/').'#business'],
        'areaServed' => ['@type' => 'City', 'name' => 'Tromsø'],
    ];
@endphp

<x-layouts.storefront :title="$service->seoTitle ?? ('EasyTire | '.$service->title)" :description="$service->seoDescription" :canonical="$canonicalUrl ?? null" :image="$heroUrl ?: '/brand/easytire-logo.png'" :structured-data="[$serviceSchema]" active-section="services">
    <article>
        <section class="et-service-hero">
            <div class="et-service-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span><a href="{{ route($prefix.'services.index') }}">{{ __('pro.nav.services') }}</a></span><span>{{ $service->title }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.services.kicker') }}</p>
                <h1 class="et-h1">{{ $service->title }}</h1>
                @if ($service->excerpt)<p class="et-lead et-lead--light">{{ $service->excerpt }}</p>@endif
                <div class="et-page-actions et-page-actions--hero">
                    @if ($hasSeasonalIntentChoice)
                        <a class="et-btn et-btn--primary" href="{{ $mobileRequestHref }}" data-testid="service-hero-mobile">{{ __('services.mobile_intent_cta') }}</a>
                        <a class="et-btn et-btn--ghost" href="{{ $workshopRequestHref }}" data-testid="service-hero-workshop">{{ __('services.workshop_intent_cta') }}</a>
                    @else
                        <a class="et-btn et-btn--primary" href="{{ $primaryCtaHref }}" data-testid="{{ $primaryCtaTestId }}">{{ $primaryCtaLabel }}</a>
                        @unless ($isPhoneOnlyService)<a class="et-btn et-btn--ghost" href="tel:{{ $phoneHref }}">{{ __('services.call_cta') }} {{ $phoneDisplay }}</a>@endunless
                    @endif
                </div>
            </div>
            <div class="et-service-hero__media et-service-hero__media--{{ $serviceMedia['crop'] }}" data-media-kind="{{ $serviceMedia['kind'] }}" data-media-source="{{ $serviceMedia['source'] }}">
                @if ($heroUrl)
                    <img data-testid="page-hero" src="{{ $heroUrl }}" alt="{{ $heroAlt }}" @if($service->hero) loading="eager" @else loading="lazy" @endif>
                @else
                    <div class="et-service-hero__fallback" data-testid="page-hero-fallback">
                        <img src="/brand/easytire-mark.svg" width="120" height="120" alt="">
                        <span>{{ $service->title }}</span>
                    </div>
                @endif
            </div>
        </section>

        <div class="et-service-anchor">
            <div class="et-container et-service-anchor__inner">
                <nav aria-label="{{ $service->title }}">
                    <a href="#service-details">{{ __('pro.services.detail_heading') }}</a>
                    <a href="#service-request-final">{{ __('pro.services.request_kicker') }}</a>
                </nav>
            </div>
        </div>

        <section class="et-section et-section--white et-service-detail" id="service-details">
            <div class="et-container">
                <div class="et-service-detail__top">
                    <div class="et-service-detail__intro">
                    <h2 class="et-h2">{{ __('pro.services.detail_heading') }}</h2>
                    @if ($introBody)
                        <div class="et-richtext et-service-detail__copy">{!! \Illuminate\Support\Str::markdown($introBody, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @elseif ($service->excerpt)
                        <p class="et-lead">{{ $service->excerpt }}</p>
                    @endif
                    </div>
                </div>

                @if ($remainingBody || ($servicePrices ?? collect())->isNotEmpty())
                    <div class="et-service-detail__continuation">
                        @if ($remainingBody)
                            <div class="et-richtext et-service-detail__copy">{!! \Illuminate\Support\Str::markdown($remainingBody, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        @endif
                        @if (($servicePrices ?? collect())->isNotEmpty())
                            <section class="et-price-panel" aria-labelledby="service-price-heading" data-testid="service-price-list">
                                <h2 class="et-h3" id="service-price-heading">{{ __('services.current_prices') }}</h2>
                                @foreach ($servicePrices as $categoryPrices)
                                    @php
                                        $category = $categoryPrices->first();
                                        $categoryAction = match ((string) $category->category_code) {
                                            'workshop' => [route($prefix.'requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop']), __('services.workshop_intent_cta')],
                                            'mobile' => [route($prefix.'requests.create', ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile']), __('services.mobile_intent_cta')],
                                            'hotel' => [route($prefix.'requests.create', ['slug' => 'dekkhotell']), __('services.request_cta')],
                                            default => [route($prefix.'pricing.index'), __('services.all_prices')],
                                        };
                                    @endphp
                                    <h3>{{ $category->localized('category_title', app()->getLocale()) }}</h3>
                                    @foreach ($categoryPrices as $price)
                                        <div class="et-public-price-row">
                                            <div><strong>{{ $price->localized('title', app()->getLocale()) }}</strong>@if($description = $price->localized('description', app()->getLocale()))<p>{{ $description }}</p>@endif<a class="et-btn et-btn--link et-btn--small" href="{{ $categoryAction[0] }}">{{ $categoryAction[1] }}</a></div>
                                            <strong class="et-public-price-row__amount"><x-storefront-money :minor="$price->price_minor" :currency="$price->currency" :decimals="0" /></strong>
                                        </div>
                                    @endforeach
                                @endforeach
                                <a class="et-btn et-btn--link" href="{{ route($prefix.'pricing.index') }}">{{ __('services.all_prices') }}</a>
                            </section>
                        @endif
                    </div>
                @endif

                <section class="et-service-final-action" id="service-request-final" aria-labelledby="service-final-action-title">
                    <div>
                        <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                        <h2 class="et-h2" id="service-final-action-title">{{ __('pro.services.request_title') }}</h2>
                        <p class="et-lead">{{ __('pro.services.request_text') }}</p>
                    </div>
                    <div class="et-page-actions">
                        @if ($hasSeasonalIntentChoice)
                            <a class="et-btn et-btn--primary" href="{{ $mobileRequestHref }}" data-testid="service-final-mobile">{{ __('services.mobile_intent_cta') }}</a>
                            <a class="et-btn et-btn--outline" href="{{ $workshopRequestHref }}" data-testid="service-final-workshop">{{ __('services.workshop_intent_cta') }}</a>
                        @else
                            <a class="et-btn et-btn--primary" href="{{ $primaryCtaHref }}" data-testid="{{ $isPhoneOnlyService ? 'service-final-call' : 'service-final-request' }}">{{ $primaryCtaLabel }}</a>
                        @endif
                    </div>
                </section>
            </div>
        </section>
    </article>
</x-layouts.storefront>
