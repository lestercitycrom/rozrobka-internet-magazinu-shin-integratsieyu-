@php
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $serviceCollection = collect($services ?? []);
    $seasonalSlugs = [
        'mobilt-dekkskift',
        'dekkskift',
        'dekkskift-tromso',
        'dekkskift-verksted',
        'dekkverksted',
        'hjulskift',
        'sesongskift',
    ];
    $isSeasonalService = static function ($service) use ($seasonalSlugs): bool {
        $declaredFlow = data_get($service, 'requestFlow')
            ?? data_get($service, 'request_flow')
            ?? data_get($service, 'booking_flow')
            ?? data_get($service, 'flow_type');
        if ($declaredFlow instanceof \BackedEnum) {
            $declaredFlow = $declaredFlow->value;
        }
        $declaredFlow = is_string($declaredFlow) ? mb_strtolower(trim($declaredFlow)) : null;
        if (in_array($declaredFlow, ['seasonal', 'seasonal_booking', 'dekkskift'], true)) {
            return true;
        }
        if (in_array($declaredFlow, ['generic', 'generic_request', 'service_request'], true)) {
            return false;
        }

        $declaredSeasonal = data_get($service, 'is_seasonal');
        if (in_array($declaredSeasonal, [true, 1, '1', 'true'], true)) {
            return true;
        }
        if (in_array($declaredSeasonal, [false, 0, '0', 'false'], true)) {
            return false;
        }

        return in_array(mb_strtolower((string) data_get($service, 'slug')), $seasonalSlugs, true);
    };
    $seasonalService = $serviceCollection->first($isSeasonalService);
    $hotelService = $serviceCollection->first(
        fn ($service) => in_array(mb_strtolower((string) data_get($service, 'slug')), ['dekkhotell', 'dekkhotell-tromso'], true)
    );
    $bookingUrl = $seasonalService
        ? route($routePrefix.'requests.create', $seasonalService->slug)
        : route($routePrefix.'services.index');
    $contactPages = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('content_pages')) {
            $contactPages = app(\App\Modules\Content\ReadModel\PublishedContentPageReadService::class)
                ->publishedSlugs(['kontakt']);
        }
    } catch (\Throwable) {
        $contactPages = [];
    }
    $contactUrl = isset($contactPages['kontakt'])
        ? route($routePrefix.'content.show', 'kontakt')
        : 'mailto:max.easytire@gmail.com';
    $mapUrl = 'https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic';
    $mapEmbedUrl = \App\Support\GoogleMapsEmbedUrl::sanitize(config('storefront.map_embed_url'));
    $homepageLocale = app()->getLocale() === 'ru' ? 'ru' : 'nb';
    $homepagePromotions = collect();
    $homepageReviews = collect();
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_promotions')) {
            $homepagePromotions = \App\Modules\Content\Models\HomepagePromotion::query()->published()->orderBy('sort_order')->orderBy('id')->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_reviews')) {
            $homepageReviews = \App\Modules\Content\Models\HomepageReview::query()->published()->orderBy('sort_order')->orderBy('id')->get();
        }
    } catch (\Throwable) {
        $homepagePromotions = collect();
        $homepageReviews = collect();
    }
@endphp

<x-layouts.storefront :title="__('storefront.site_title')" :description="__('storefront.site_description')">
    <section class="et-hero">
        <img class="et-hero__photo" src="/brand/hero-mobile.webp" width="1920" height="1280" alt="{{ __('pro.home.hero_image_alt') }}" fetchpriority="high">
        <div class="et-container et-hero__inner">
            <div class="et-hero__content">
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.hero_kicker') }}</p>
                <h1 class="et-display">{{ __('pro.home.hero_title') }}</h1>
                <p class="et-hero__sub">{{ __('pro.home.hero_text') }}</p>
                <div class="et-hero__actions">
                    <a class="et-btn et-btn--primary" href="{{ $bookingUrl }}">{{ __('pro.home.hero_primary') }}</a>
                    <a class="et-btn et-btn--ghost" href="{{ route($routePrefix.'store.index') }}">{{ __('pro.home.hero_secondary') }}</a>
                </div>
                <div class="et-hero__proof" aria-label="{{ __('pro.home.confirmation_note') }}">
                    <span>{{ __('pro.home.hero_proof_mobile') }}</span>
                    <span>{{ __('pro.home.hero_proof_workshop') }}</span>
                    <span>{{ __('pro.home.hero_proof_hotel') }}</span>
                </div>
            </div>

            <aside class="et-hero-card" aria-labelledby="home-location-title">
                <div class="et-hero-card__head">
                    <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.start_here') }}</p>
                    <h2 class="et-hero-card__title" id="home-location-title">{{ __('pro.home.location_title') }}</h2>
                </div>
                <div class="et-location-choice">
                    <a class="et-location-option" href="{{ $bookingUrl }}?fulfilment=mobile">
                        <span class="et-location-option__icon" aria-hidden="true">H</span>
                        <span><strong>{{ __('pro.home.location_mobile') }}</strong><small>{{ __('pro.home.location_mobile_text') }}</small></span>
                        <span class="et-location-option__arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="et-location-option" href="{{ $bookingUrl }}?fulfilment=workshop">
                        <span class="et-location-option__icon" aria-hidden="true">V</span>
                        <span><strong>{{ __('pro.home.location_workshop') }}</strong><small>{{ __('pro.home.location_workshop_text') }}</small></span>
                        <span class="et-location-option__arrow" aria-hidden="true">→</span>
                    </a>
                </div>
                <div class="et-hero-card__store">
                    <span>{{ __('pro.home.need_wheels') }}</span>
                    <a href="{{ route($routePrefix.'store.index') }}">{{ __('pro.home.search_registration') }}</a>
                </div>
            </aside>
        </div>
        <div class="et-season-ribbon"><span aria-hidden="true">✓</span>{{ __('pro.home.confirmation_note') }}</div>
    </section>

    <section class="et-choice-band" aria-label="{{ __('pro.home.start_here') }}">
        <div class="et-container">
            <div class="et-choice-band__grid">
                <article class="et-choice-band__item easytire-card easytire-interactive">
                    <span class="et-choice-band__number">{{ __('pro.home.choice_fast') }}</span>
                    <h2>{{ __('pro.home.choice_fast_title') }}</h2>
                    <p>{{ __('pro.home.choice_fast_text') }}</p>
                    <a href="{{ $bookingUrl }}">{{ __('pro.home.choice_fast_link') }}</a>
                </article>
                <article class="et-choice-band__item easytire-card easytire-interactive">
                    <span class="et-choice-band__number">{{ __('pro.home.choice_store') }}</span>
                    <h2>{{ __('pro.home.choice_store_title') }}</h2>
                    <p>{{ __('pro.home.choice_store_text') }}</p>
                    <a href="{{ route($routePrefix.'store.index') }}">{{ __('pro.home.choice_store_link') }}</a>
                </article>
                <article class="et-choice-band__item easytire-card easytire-interactive">
                    <span class="et-choice-band__number">{{ __('pro.home.choice_help') }}</span>
                    <h2>{{ __('pro.home.choice_help_title') }}</h2>
                    <p>{{ __('pro.home.choice_help_text') }}</p>
                    <a href="{{ $contactUrl }}">{{ __('pro.home.choice_help_link') }}</a>
                </article>
            </div>
        </div>
    </section>

    @if ($homepagePromotions->isNotEmpty())
        <section class="et-section et-section--white" aria-labelledby="home-promotions-title" data-testid="homepage-promotions">
            <div class="et-container">
                <p class="et-eyebrow">{{ __('pro.home.promotions_kicker') }}</p>
                <h2 class="et-h2" id="home-promotions-title">{{ __('pro.home.promotions_title') }}</h2>
                <div class="et-service-grid" style="margin-top:2rem">
                    @foreach ($homepagePromotions as $promotion)
                        @php
                            $title = $promotion->getAttribute('title_'.$homepageLocale);
                            $body = $promotion->getAttribute('body_'.$homepageLocale);
                            $ctaLabel = $promotion->getAttribute('cta_label_'.$homepageLocale) ?: __('pro.home.promotion_link_fallback');
                            $ctaUrl = $promotion->cta_url;
                            $externalPromotion = is_string($ctaUrl) && str_starts_with($ctaUrl, 'http');
                        @endphp
                        <article class="et-service-card easytire-card" data-testid="homepage-promotion">
                            <div class="et-service-card__top"><span class="et-service-card__index">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span><span class="et-service-card__icon" aria-hidden="true">+</span></div>
                            <div class="et-service-card__body">
                                <h3>{{ $title }}</h3>
                                @if ($body)<p>{{ $body }}</p>@endif
                                @if ($ctaUrl)<a href="{{ $ctaUrl }}" @if($externalPromotion) target="_blank" rel="noopener" @endif>{{ $ctaLabel }}</a>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($serviceCollection->isNotEmpty())
    <section class="et-section et-section--white" aria-labelledby="home-services-title" data-testid="services-section">
        <div class="et-container">
            <div class="et-cluster" style="justify-content:space-between;align-items:end">
                <div>
                    <p class="et-eyebrow">{{ __('pro.home.services_kicker') }}</p>
                    <h2 class="et-h2" id="home-services-title">{{ __('pro.home.services_title') }}</h2>
                    <p class="et-lead">{{ __('pro.home.services_text') }}</p>
                </div>
                <a class="et-btn et-btn--outline" href="{{ route($routePrefix.'services.index') }}">{{ __('pro.home.services_all') }}</a>
            </div>

            <div class="et-service-grid">
                @forelse ($serviceCollection->take(5) as $index => $service)
                    <article @class(['et-service-card easytire-card easytire-interactive', 'et-service-card--feature' => $index === 0])>
                        <div class="et-service-card__top">
                            <span class="et-service-card__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="et-service-card__icon" aria-hidden="true">{{ $index === 0 ? '↻' : ($index === 1 ? 'V' : ($index === 2 ? 'D' : '+')) }}</span>
                        </div>
                        <div class="et-service-card__body">
                            <h3>{{ $service->title }}</h3>
                            @if ($service->excerpt)<p>{{ $service->excerpt }}</p>@endif
                            <a href="{{ route($routePrefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ __('pro.home.service_link') }}</a>
                        </div>
                    </article>
                @empty
                    <article class="et-card et-card--flat"><div class="et-card__body"><p class="et-copy">{{ __('pro.home.services_empty') }}</p></div></article>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    <section class="et-section et-section--grid" aria-labelledby="home-process-title">
        <div class="et-container">
            <p class="et-eyebrow">{{ __('pro.home.process_kicker') }}</p>
            <h2 class="et-h2" id="home-process-title">{{ __('pro.home.process_title') }}</h2>
            <div class="et-process">
                @foreach ([
                    [__('pro.home.process_1_title'), __('pro.home.process_1_text')],
                    [__('pro.home.process_2_title'), __('pro.home.process_2_text')],
                    [__('pro.home.process_3_title'), __('pro.home.process_3_text')],
                    [__('pro.home.process_4_title'), __('pro.home.process_4_text')],
                ] as $index => [$stepTitle, $stepText])
                    <article class="et-process__step">
                        <span class="et-process__number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ $stepTitle }}</h3>
                        <p>{{ $stepText }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="et-section et-section--white" aria-labelledby="home-store-title" data-testid="catalog-foundation">
        <div class="et-container">
            <div class="et-store-panel">
                <div class="et-store-panel__intro">
                    <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.store_kicker') }}</p>
                    <h2 class="et-h2" id="home-store-title">{{ __('pro.home.store_title') }}</h2>
                    <p>{{ __('pro.home.store_text') }}</p>
                    <a class="et-btn et-btn--ghost" href="{{ route($routePrefix.'store.index') }}">{{ __('pro.home.store_link') }}</a>
                </div>

                <div class="et-store-panel__tools" data-tabs>
                    <div class="et-search-tabs" role="tablist" aria-label="{{ __('pro.home.store_title') }}">
                        <button id="home-tab-registration" type="button" role="tab" aria-selected="true" aria-controls="home-panel-registration">{{ __('pro.home.tab_registration') }}</button>
                        <button id="home-tab-manual" type="button" role="tab" aria-selected="false" aria-controls="home-panel-manual" tabindex="-1">{{ __('pro.home.tab_manual') }}</button>
                    </div>

                    <div id="home-panel-registration" role="tabpanel" aria-labelledby="home-tab-registration">
                        <form method="POST" action="{{ route($routePrefix.'store.lookup') }}">
                            @csrf
                            <label class="et-label" for="home-registration">{{ __('pro.home.registration_label') }}</label>
                            <div class="et-reg-search">
                                <div class="et-reg-input">
                                    <input class="et-input" id="home-registration" name="registration" required maxlength="16" autocomplete="off" autocapitalize="characters" placeholder="{{ __('pro.home.registration_placeholder') }}">
                                </div>
                                <button class="et-btn et-btn--primary" type="submit">{{ __('pro.home.registration_submit') }}</button>
                            </div>
                            <p class="et-help">{{ __('pro.home.registration_help') }}</p>
                        </form>
                    </div>

                    <div id="home-panel-manual" role="tabpanel" aria-labelledby="home-tab-manual" hidden>
                        <p class="et-copy">{{ __('pro.home.manual_text') }}</p>
                        <div class="et-shop-links">
                            <a class="et-shop-link" href="{{ route($routePrefix.'tires.index') }}"><strong>{{ __('pro.home.manual_tires') }}</strong><small>{{ __('storefront.width') }} · {{ __('storefront.aspect') }} · {{ __('storefront.rim') }}</small></a>
                            <a class="et-shop-link" href="{{ route($routePrefix.'wheels.index') }}"><strong>{{ __('pro.home.manual_wheels') }}</strong><small>{{ __('storefront.diameter') }} · {{ __('storefront.pcd') }} · {{ __('storefront.offset') }}</small></a>
                            <a class="et-shop-link" href="{{ route($routePrefix.'store.index') }}"><strong>{{ __('fitment.nav') }}</strong><small>{{ __('fitment.notice') }}</small></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="et-section" aria-labelledby="home-story-title">
        <div class="et-container et-split">
            <div class="et-photo-pair">
                <div class="et-photo-pair__main"><img src="/brand/founders.webp" width="1400" height="1050" loading="lazy" alt="EasyTire-teamet i Tromsø"></div>
                <div class="et-photo-pair__small"><img src="/brand/founders-close.webp" width="1000" height="1000" loading="lazy" alt="EasyTire-teamet på verkstedet"></div>
            </div>
            <div>
                <p class="et-eyebrow">{{ __('pro.home.story_kicker') }}</p>
                <h2 class="et-h2" id="home-story-title">{{ __('pro.home.story_title') }}</h2>
                <p class="et-lead">{{ __('pro.home.story_text') }}</p>
                <blockquote class="et-quote">{{ __('pro.home.story_quote') }}</blockquote>
                <ul class="et-fact-list">
                    <li><span><strong>{{ __('pro.home.story_fact_1') }}</strong><br><span class="et-caption">{{ __('pro.home.story_fact_1_text') }}</span></span></li>
                    <li><span><strong>{{ __('pro.home.story_fact_2') }}</strong><br><span class="et-caption">{{ __('pro.home.story_fact_2_text') }}</span></span></li>
                    <li><span><strong>{{ __('pro.home.story_fact_3') }}</strong><br><span class="et-caption">{{ __('pro.home.story_fact_3_text') }}</span></span></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="et-section et-section--white" aria-labelledby="home-logistics-title">
        <div class="et-container">
            <p class="et-eyebrow">{{ __('pro.home.hero_proof_hotel') }} · {{ __('pro.home.hero_proof_mobile') }}</p>
            <h2 class="et-h2" id="home-logistics-title">{{ __('pro.home.location_title') }}</h2>
            <div class="et-promo-grid">
                <article class="et-promo">
                    <img src="/brand/dekkhotell.webp" width="1000" height="1200" loading="lazy" alt="Dekk lagret hos EasyTire">
                    <div class="et-promo__content">
                        <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.hotel_kicker') }}</p>
                        <h3 class="et-h2">{{ __('pro.home.hotel_title') }}</h3>
                        <p>{{ __('pro.home.hotel_text') }}</p>
                        <a class="et-btn et-btn--ghost" href="{{ $hotelService ? route($routePrefix.($hotelService->canonicalPath ? 'seo-services.show' : 'services.show'), $hotelService->canonicalPath ?: $hotelService->slug) : route($routePrefix.'services.index') }}">{{ __('pro.home.hotel_link') }}</a>
                    </div>
                </article>
                <article class="et-promo et-promo--red">
                    <img src="/brand/mobile-service.webp" width="1200" height="900" loading="lazy" alt="EasyTire mobil service">
                    <div class="et-promo__content">
                        <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.mobile_kicker') }}</p>
                        <h3 class="et-h2">{{ __('pro.home.mobile_title') }}</h3>
                        <p>{{ __('pro.home.mobile_text') }}</p>
                        <a class="et-btn et-btn--ghost" href="{{ $bookingUrl }}?fulfilment=mobile">{{ __('pro.home.mobile_link') }}</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="et-section et-section--grid" aria-labelledby="home-business-title" data-testid="business-contact-card">
        <div class="et-container">
            <article class="et-promo et-promo--red">
                <img src="/brand/exterior.webp" width="1600" height="1067" loading="lazy" alt="{{ __('pro.home.business_image_alt') }}">
                <div class="et-promo__content">
                    <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.business_kicker') }}</p>
                    <h2 class="et-h2" id="home-business-title">{{ __('pro.home.business_title') }}</h2>
                    <p>{{ __('pro.home.business_text') }}</p>
                    <a class="et-btn et-btn--ghost" href="{{ $contactUrl }}">{{ __('pro.home.business_link') }}</a>
                </div>
            </article>
        </div>
    </section>

    @if ($homepageReviews->isNotEmpty())
        <section class="et-section et-section--white" aria-labelledby="home-reviews-title" data-testid="homepage-reviews">
            <div class="et-container">
                <p class="et-eyebrow">{{ __('pro.home.reviews_kicker') }}</p>
                <h2 class="et-h2" id="home-reviews-title">{{ __('pro.home.reviews_title') }}</h2>
                <div class="et-service-grid" style="margin-top:2rem">
                    @foreach ($homepageReviews as $review)
                        @php
                            $quote = $review->getAttribute('quote_'.$homepageLocale);
                            $author = $review->getAttribute('author_'.$homepageLocale);
                            $sourceLabel = $review->getAttribute('source_label_'.$homepageLocale) ?: __('pro.home.reviews_link_fallback');
                        @endphp
                        <article class="et-service-card easytire-card" data-testid="homepage-review">
                            <div class="et-service-card__top"><span class="et-service-card__index">{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span><span class="et-service-card__icon" aria-hidden="true">“</span></div>
                            <div class="et-service-card__body">
                                @if ($quote)<blockquote class="et-copy" style="margin:0">“{{ $quote }}”</blockquote>@endif
                                @if ($author)<p class="et-caption" style="margin-top:1rem">{{ $author }}</p>@endif
                                <a href="{{ $review->source_url }}" target="_blank" rel="noopener">{{ $sourceLabel }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="et-section et-section--ink" aria-labelledby="home-contact-title">
        <div class="et-container et-split">
            <div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.contact_kicker') }}</p>
                <h2 class="et-h2" id="home-contact-title">{{ __('pro.home.contact_title') }}</h2>
                <p class="et-lead et-lead--light">{{ __('pro.home.contact_text') }}</p>
                <div class="et-page-actions" style="margin-top:1.5rem">
                    <a class="et-btn et-btn--light" href="tel:+4793958270">{{ __('pro.home.call') }}</a>
                    <a class="et-btn et-btn--ghost" href="mailto:max.easytire@gmail.com">{{ __('pro.home.email') }}</a>
                </div>
            </div>
            <div class="et-map" data-map-card data-testid="map-card" data-map-href="{{ $mapUrl }}" @if($mapEmbedUrl !== null) data-map-embed-url="{{ $mapEmbedUrl }}" @endif data-map-ready-title="{{ __('pro.content.map_ready_title') }}" data-map-ready-text="{{ __('pro.content.map_ready_text') }}" data-map-link-label="{{ __('pro.content.map_link') }}">
                <div class="et-map__card">
                    <div class="et-map__pin" aria-hidden="true"><span>●</span></div>
                    <h3 class="et-h3">{{ __('pro.content.map_title') }}</h3>
                    <p>{{ __('pro.content.map_privacy') }}</p>
                    @if ($mapEmbedUrl !== null)
                        <button class="et-btn et-btn--primary et-btn--full" type="button" data-map-consent data-testid="map-consent">{{ __('pro.content.map_activate') }}</button>
                    @else
                        <a class="et-btn et-btn--primary et-btn--full" href="{{ $mapUrl }}" target="_blank" rel="noopener" data-testid="map-external-only">{{ __('pro.content.map_options') }}</a>
                    @endif
                    <a class="et-btn et-btn--link" href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.home.map') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.storefront>
