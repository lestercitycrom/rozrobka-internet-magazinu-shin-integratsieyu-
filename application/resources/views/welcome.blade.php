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
    $storefrontSettings = $settings ?? null;
    $storefrontValue = static function (string $field, string $fallback) use ($storefrontSettings): string {
        $value = $storefrontSettings?->getAttribute($field);

        return filled($value) ? (string) $value : $fallback;
    };
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
    $phoneDisplay = config('storefront.phone_display');
    $phoneHref = config('storefront.phone_href');
    $contactEmail = config('storefront.email');
    $businessAddress = config('storefront.business_address');
    $mapPinAddress = config('storefront.map_pin_address');
    $mapUrl = config('storefront.map_url');
    $mapEmbedUrl = \App\Support\GoogleMapsEmbedUrl::sanitize(config('storefront.map_embed_url'));
    $homepageLocale = app()->getLocale() === 'ru' ? 'ru' : 'nb';
    $phoneDisplay = $storefrontValue('phone_display', (string) $phoneDisplay);
    $phoneHref = $storefrontValue('phone_href', (string) $phoneHref);
    $contactEmail = $storefrontValue('email', (string) $contactEmail);
    $businessAddress = $storefrontValue('business_address', (string) $businessAddress);
    $mapPinAddress = $storefrontValue('map_pin_address', (string) $mapPinAddress);
    $mapUrl = $storefrontValue('map_url', (string) $mapUrl);
    $mapEmbedUrl = \App\Support\GoogleMapsEmbedUrl::sanitize($storefrontValue('map_embed_url', (string) config('storefront.map_embed_url')));
    $heroPrimaryUrl = $storefrontValue('hero_primary_url', $bookingUrl);
    $heroImage = $storefrontSettings?->heroMedia
        ? \Illuminate\Support\Facades\Storage::disk($storefrontSettings->heroMedia->disk)->url($storefrontSettings->heroMedia->path)
        : '/brand/hero-service-desktop.webp';
    $heroMobileImage = $storefrontSettings?->heroMedia ? $heroImage : '/brand/hero-service-mobile.webp';
    $heroImageAlt = $storefrontSettings?->heroMedia?->getAttribute('alt_'.$homepageLocale) ?: __('pro.home.hero_image_alt');
    $sectionImage = static function (string $relation, string $fallback, string $fallbackAlt) use ($storefrontSettings, $homepageLocale): array {
        $asset = $storefrontSettings?->{$relation};

        return $asset
            ? [\Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path), $asset->getAttribute('alt_'.$homepageLocale) ?: $fallbackAlt]
            : [$fallback, $fallbackAlt];
    };
    [$homeStoreImage, $homeStoreImageAlt] = $sectionImage('homeStoreMedia', '/brand/workshop.webp', __('pro.content.workshop_image_alt'));
    [$hotelImage, $hotelImageAlt] = $sectionImage('hotelMedia', '/brand/dekkhotell.webp', __('pro.home.hotel_image_alt'));
    [$mobileImage, $mobileImageAlt] = $sectionImage('mobileMedia', '/brand/mobile-service.webp', __('pro.home.hero_image_alt'));
    $promotionFallbackImages = [$homeStoreImage, $hotelImage, $mobileImage];
    $storedSocialLinks = app(\App\Modules\Content\ReadModel\StorefrontSettingsReadService::class)->socialLinks();
    $socialLinks = collect([
        ['label' => __('pro.home.social_facebook'), 'url' => $storedSocialLinks['facebook_url']],
        ['label' => __('pro.home.social_instagram'), 'url' => $storedSocialLinks['instagram_url']],
        ['label' => __('pro.home.social_snapchat'), 'url' => $storedSocialLinks['snapchat_url']],
    ])->filter(fn (array $social): bool => $social['url'] !== null);
    $homepagePromotions = collect();
    $homepageReviews = collect();
    $homepageRatingSummaries = collect();
    $homepageBusinessPartners = collect();
    $homepagePriceGroups = collect();
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_promotions')) {
            $homepagePromotions = \App\Modules\Content\Models\HomepagePromotion::query()->published()->with('mediaAsset')->orderBy('sort_order')->orderBy('id')->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_reviews')) {
            $homepageReviews = \App\Modules\Content\Models\HomepageReview::query()->published()->orderBy('sort_order')->orderBy('id')->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_rating_summaries')) {
            $homepageRatingSummaries = \App\Modules\Content\Models\HomepageRatingSummary::query()->published()->orderBy('sort_order')->orderBy('id')->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_business_partners')) {
            $homepageBusinessPartners = \App\Modules\Content\Models\HomepageBusinessPartner::query()
                ->published()
                ->whereHas('mediaAsset', fn ($query) => $query->where('is_active', true))
                ->with('mediaAsset')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('public_service_prices')) {
            $homepagePriceGroups = \App\Modules\Commerce\Models\PublicServicePrice::query()
                ->published()
                ->orderBy('sort_order')
                ->get()
                ->groupBy('category_code')
                ->map(fn ($prices) => $prices->sortBy('price_minor')->first())
                ->take(3);
        }
    } catch (\Throwable) {
        $homepagePromotions = collect();
        $homepageReviews = collect();
        $homepageRatingSummaries = collect();
        $homepageBusinessPartners = collect();
        $homepagePriceGroups = collect();
    }
    $homepagePromotions = $homepagePromotions->filter(
        fn ($promotion) => filled($promotion->getAttribute('title_'.$homepageLocale))
            && filled($promotion->getAttribute('body_'.$homepageLocale))
    );
    $homepageReviews = $homepageReviews->filter(
        fn ($review) => filled($review->getAttribute('quote_'.$homepageLocale))
            && \App\Support\PublicHttpUrl::isValid($review->source_url)
    );
    $homepageRatingSummaries = $homepageRatingSummaries->filter(
        fn ($summary) => filled($summary->getAttribute('count_label_'.$homepageLocale))
            && filled($summary->getAttribute('link_label_'.$homepageLocale))
            && $summary->publicSourceUrl() !== null
    );
    $serviceUrl = static fn ($service): string => route(
        $routePrefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'),
        $service->canonicalPath ?: $service->slug
    );
    $serviceImage = static function ($service, int $index): string {
        $publishedHero = data_get($service, 'hero.url');
        if (is_string($publishedHero) && $publishedHero !== '') {
            return $publishedHero;
        }

        $slug = mb_strtolower((string) data_get($service, 'slug'));
        return match (true) {
            str_contains($slug, 'hotell') => '/brand/dekkhotell.webp',
            str_contains($slug, 'mobil') => '/brand/hero-mobile.webp',
            str_contains($slug, 'verksted'), str_contains($slug, 'balanser') => '/brand/workshop.webp',
            str_contains($slug, 'tesla'), str_contains($slug, 'elbil') => '/brand/mobile-service.webp',
            default => $index % 2 === 0 ? '/brand/workshop-alt.webp' : '/brand/exterior.webp',
        };
    };
@endphp

<x-layouts.storefront :title="__('storefront.site_title')" :description="__('storefront.site_description')">
    <section class="et-v3-hero">
        <picture>
            <source media="(max-width: 640px)" srcset="{{ $heroMobileImage }}">
            <img class="et-v3-hero__photo" src="{{ $heroImage }}" width="1920" height="1080" alt="{{ $heroImageAlt }}" fetchpriority="high">
        </picture>
        <div class="et-v3-hero__shade" aria-hidden="true"></div>
        <div class="et-container et-v3-hero__inner">
            <div class="et-v3-hero__content">
                <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('hero_kicker_'.$homepageLocale, __('pro.home.hero_kicker')) }}</p>
                <h1 class="et-v3-display">{{ $storefrontValue('hero_title_'.$homepageLocale, __('pro.home.hero_title')) }}</h1>
                <p class="et-v3-hero__lead">{{ $storefrontValue('hero_text_'.$homepageLocale, __('pro.home.hero_text')) }}</p>
                <div class="et-v3-actions">
                    <a class="et-btn et-btn--primary et-v3-hero__primary-action" href="{{ $heroPrimaryUrl }}">{{ $storefrontValue('hero_primary_label_'.$homepageLocale, __('pro.home.hero_primary')) }} <x-icon name="arrow" :size="20" /></a>
                </div>
                <ul class="et-v3-hero__proof" aria-label="{{ __('pro.home.confirmation_note') }}">
                    <li><x-icon name="check" :size="16" />{{ __('pro.home.hero_proof_mobile') }}</li>
                    <li><x-icon name="check" :size="16" />{{ __('pro.home.hero_proof_workshop') }}</li>
                    <li><x-icon name="check" :size="16" />{{ __('pro.home.hero_proof_hotel') }}</li>
                </ul>
            </div>

            <aside aria-label="{{ __('pro.home.finder_title') }}">
                @include('storefront._quick-finder', ['idPrefix' => 'home-finder'])
            </aside>
        </div>
    </section>

    <section class="et-v3-section et-v3-section--white et-v3-logistics" aria-labelledby="home-logistics-title" data-testid="home-dekkhotell">
        <div class="et-container">
            <div class="et-v3-section-head et-v3-section-head--compact"><div><p class="et-eyebrow">{{ $storefrontValue('logistics_kicker_'.$homepageLocale, __('pro.home.hero_proof_hotel').' · '.__('pro.home.hero_proof_mobile')) }}</p><h2 class="et-h2" id="home-logistics-title">{{ $storefrontValue('logistics_title_'.$homepageLocale, __('pro.home.dekkhotell_section_title')) }}</h2></div></div>
            <div class="et-v3-editorial-grid">
                <article class="et-v3-editorial">
                    <img src="{{ $hotelImage }}" width="1000" height="1200" loading="eager" alt="{{ $hotelImageAlt }}">
                    <div>
                        <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('hotel_kicker_'.$homepageLocale, __('pro.home.hotel_kicker')) }}</p>
                        <h3>{{ $storefrontValue('hotel_title_'.$homepageLocale, __('pro.home.hotel_title')) }}</h3>
                        <p>{{ $storefrontValue('hotel_text_'.$homepageLocale, __('pro.home.hotel_text')) }}</p>
                        <a href="{{ $hotelService ? $serviceUrl($hotelService) : route($routePrefix.'services.index') }}">{{ $storefrontValue('hotel_cta_'.$homepageLocale, __('pro.home.hotel_link')) }} <x-icon name="arrow" :size="18" /></a>
                    </div>
                </article>
                <article class="et-v3-editorial">
                    <img src="{{ $mobileImage }}" width="1200" height="900" loading="eager" alt="{{ $mobileImageAlt }}">
                    <div>
                        <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('mobile_kicker_'.$homepageLocale, __('pro.home.mobile_kicker')) }}</p>
                        <h3>{{ $storefrontValue('mobile_title_'.$homepageLocale, __('pro.home.mobile_title')) }}</h3>
                        <p>{{ $storefrontValue('mobile_text_'.$homepageLocale, __('pro.home.mobile_text')) }}</p>
                        <a href="{{ $bookingUrl }}?fulfilment=mobile">{{ $storefrontValue('mobile_cta_'.$homepageLocale, __('pro.home.mobile_link')) }} <x-icon name="arrow" :size="18" /></a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="et-v3-process-strip" aria-labelledby="home-process-title" data-testid="home-process">
        <div class="et-container et-v3-process-strip__inner">
            <div class="et-v3-process-strip__head">
                <p class="et-eyebrow">{{ $storefrontValue('process_kicker_'.$homepageLocale, __('pro.home.process_kicker')) }}</p>
                <h2 class="sr-only" id="home-process-title">{{ $storefrontValue('process_title_'.$homepageLocale, __('project.home.process_title')) }}</h2>
            </div>
            <div class="et-v3-process">
                @foreach ([
                    ['search', $storefrontValue('process_1_title_'.$homepageLocale, __('project.home.process_1_title')), $storefrontValue('process_1_text_'.$homepageLocale, __('project.home.process_1_text'))],
                    ['calendar', $storefrontValue('process_2_title_'.$homepageLocale, __('project.home.process_2_title')), $storefrontValue('process_2_text_'.$homepageLocale, __('project.home.process_2_text'))],
                    ['tire', $storefrontValue('process_3_title_'.$homepageLocale, __('project.home.process_3_title')), $storefrontValue('process_3_text_'.$homepageLocale, __('project.home.process_3_text'))],
                    ['check', $storefrontValue('process_4_title_'.$homepageLocale, __('project.home.process_4_title')), $storefrontValue('process_4_text_'.$homepageLocale, __('project.home.process_4_text'))],
                ] as $index => [$stepIcon, $stepTitle, $stepText])
                    <article class="et-v3-process__step">
                        <span class="et-v3-process__icon"><x-icon :name="$stepIcon" :size="24" /></span>
                        <small>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</small>
                        <div><h3>{{ $stepTitle }}</h3><p>{{ $stepText }}</p></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="et-home-prices" aria-labelledby="home-prices-title" data-testid="home-prices">
        <div class="et-container et-home-prices__inner">
            <header>
                <p class="et-eyebrow">{{ __('pro.home.price_kicker') }}</p>
                <h2 id="home-prices-title">{{ __('pro.home.price_title') }}</h2>
            </header>
            <div class="et-home-prices__list">
                @forelse ($homepagePriceGroups as $price)
                    <a href="{{ route($routePrefix.'pricing.index') }}">
                        <span>{{ $price->localized('category_title', $homepageLocale) }}</span>
                        <strong><small>{{ __('pro.home.price_from') }}</small> {{ number_format($price->price_minor / 100, 0, ',', ' ') }} {{ $price->currency }}</strong>
                        <x-icon name="arrow" :size="18" />
                    </a>
                @empty
                    <a href="{{ route($routePrefix.'pricing.index') }}"><span>{{ __('pro.home.price_pending') }}</span><strong>{{ __('pro.home.price_all') }}</strong><x-icon name="arrow" :size="18" /></a>
                @endforelse
            </div>
            <a class="et-home-prices__all" href="{{ route($routePrefix.'pricing.index') }}">{{ __('pro.home.price_all') }} <x-icon name="arrow" :size="17" /></a>
        </div>
    </section>

    @if ($homepagePromotions->isNotEmpty())
        <section class="et-v3-notices" aria-labelledby="home-promotions-title" data-testid="homepage-promotions">
            <div class="et-container">
                <div class="et-v3-section-head et-v3-section-head--compact">
                    <div><p class="et-eyebrow">{{ __('pro.home.promotions_kicker') }}</p><h2 class="et-h3" id="home-promotions-title">{{ __('pro.home.promotions_title') }}</h2></div>
                </div>
                <div class="et-v3-promotion-grid">
                    @foreach ($homepagePromotions as $promotion)
                        @php
                            $title = $promotion->getAttribute('title_'.$homepageLocale);
                            $body = $promotion->getAttribute('body_'.$homepageLocale);
                            $ctaLabel = $promotion->getAttribute('cta_label_'.$homepageLocale) ?: __('pro.home.promotion_link_fallback');
                            $ctaUrl = $promotion->cta_url;
                            $externalPromotion = is_string($ctaUrl) && str_starts_with($ctaUrl, 'http');
                            $promotionAsset = $promotion->mediaAsset;
                            $promotionImage = $promotionAsset
                                ? \Illuminate\Support\Facades\Storage::disk($promotionAsset->disk)->url($promotionAsset->path)
                                : $promotionFallbackImages[$loop->index % count($promotionFallbackImages)];
                            $promotionAlt = $promotionAsset?->getAttribute('alt_'.$homepageLocale) ?: '';
                        @endphp
                        <article class="et-v3-promotion" data-testid="homepage-promotion" tabindex="0">
                            <img src="{{ $promotionImage }}" width="1200" height="900" loading="lazy" alt="{{ $promotionAlt }}">
                            <span class="et-v3-promotion__shade" aria-hidden="true"></span>
                            <div class="et-v3-promotion__content">
                                <span class="et-v3-promotion__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3>{{ $title }}</h3>
                                <div class="et-v3-promotion__details">
                                    <p>{{ $body }}</p>
                                    @if ($ctaUrl)<a href="{{ $ctaUrl }}" @if($externalPromotion) target="_blank" rel="noopener" @endif>{{ $ctaLabel }} <x-icon name="arrow" :size="18" /></a>@endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($serviceCollection->isNotEmpty())
    <section class="et-v3-section et-v3-section--services" aria-labelledby="home-services-title" data-testid="services-section">
        <div class="et-container">
            <div class="et-v3-section-head">
                <div>
                    <p class="et-eyebrow">{{ __('pro.home.services_kicker') }}</p>
                    <h2 class="et-h2" id="home-services-title">{{ __('pro.home.services_title') }}</h2>
                    <p class="et-lead">{{ __('pro.home.services_text') }}</p>
                </div>
                <a class="et-v3-text-link" href="{{ route($routePrefix.'services.index') }}">{{ __('pro.home.services_all') }} <x-icon name="arrow" :size="18" /></a>
            </div>

            <div class="et-v3-service-grid">
                @forelse ($serviceCollection->take(6) as $index => $service)
                    <article @class(['et-v3-service', 'et-v3-service--feature' => $index < 2])>
                        @if ($index < 2)
                            <img src="{{ $serviceImage($service, $index) }}" width="1200" height="900" loading="lazy" alt="">
                            <span class="et-v3-service__shade" aria-hidden="true"></span>
                        @endif
                        <div class="et-v3-service__content">
                            <span class="et-v3-service__number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $service->title }}</h3>
                            @if ($service->excerpt)<p>{{ $service->excerpt }}</p>@endif
                            <a href="{{ $serviceUrl($service) }}" aria-label="{{ __('pro.home.service_link') }}: {{ $service->title }}">{{ __('pro.home.service_link') }} <x-icon name="arrow" :size="18" /></a>
                        </div>
                    </article>
                @empty
                    <article class="et-card et-card--flat"><div class="et-card__body"><p class="et-copy">{{ __('pro.home.services_empty') }}</p></div></article>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    <section class="et-v3-section et-v3-section--white" aria-labelledby="home-store-title" data-testid="catalog-foundation">
        <div class="et-container et-v3-store">
            <div class="et-v3-store__media">
                <img src="{{ $homeStoreImage }}" width="1500" height="1000" loading="lazy" alt="{{ $homeStoreImageAlt }}">
                <div class="et-v3-store__caption">
                    <p class="et-eyebrow et-eyebrow--light">{{ __('pro.home.store_kicker') }}</p>
                    <h2 class="et-h2" id="home-store-title">{{ __('pro.home.store_title') }}</h2>
                    <p>{{ __('pro.home.store_text') }}</p>
                </div>
            </div>

            <div class="et-v3-store__tools" data-tabs>
                <p class="et-eyebrow">{{ __('pro.home.start_here') }}</p>
                <h3>{{ __('pro.home.search_registration') }}</h3>
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
                                <button class="et-btn et-btn--primary" type="submit">{{ __('pro.home.registration_submit') }} <x-icon name="arrow" :size="18" /></button>
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
    </section>

    @if ($homepageReviews->isNotEmpty() || $homepageRatingSummaries->isNotEmpty())
        <section class="et-v3-section et-v3-section--soft" aria-labelledby="home-reviews-title" data-testid="homepage-reviews">
            <div class="et-container">
                <div class="et-v3-section-head"><div><p class="et-eyebrow">{{ __('pro.home.reviews_kicker') }}</p><h2 class="et-h2" id="home-reviews-title">{{ __('project.home.reviews_title') }}</h2></div></div>
                @if ($homepageRatingSummaries->isNotEmpty())
                    <div class="et-v3-rating-summary" aria-label="{{ __('pro.home.ratings_label') }}" data-testid="homepage-rating-summaries">
                        @foreach ($homepageRatingSummaries as $summary)
                            @php
                                $sourceUrl = $summary->publicSourceUrl();
                                $countLabel = $summary->getAttribute('count_label_'.$homepageLocale);
                                $linkLabel = $summary->getAttribute('link_label_'.$homepageLocale);
                            @endphp
                            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" data-testid="homepage-rating-summary">
                                <span class="et-v3-rating-summary__platform">{{ __('pro.home.rating_platform_'.$summary->platform->value) }}</span>
                                <span class="et-v3-rating-summary__score"><strong>{{ $summary->rating }}</strong><span aria-hidden="true">★★★★★</span><span class="sr-only">{{ __('pro.home.rating_out_of_five', ['rating' => $summary->rating]) }}</span></span>
                                <span class="et-v3-rating-summary__count">{{ $countLabel }}</span>
                                <span class="et-v3-rating-summary__link">{{ $linkLabel }} <x-icon name="arrow" :size="17" /></span>
                            </a>
                        @endforeach
                    </div>
                @endif
                @if ($homepageReviews->isNotEmpty())
                    <div class="et-v3-review-grid">
                    @foreach ($homepageReviews as $review)
                        @php
                            $quote = $review->getAttribute('quote_'.$homepageLocale);
                            $author = $review->getAttribute('author_'.$homepageLocale);
                            $sourceLabel = $review->getAttribute('source_label_'.$homepageLocale) ?: __('pro.home.reviews_link_fallback');
                        @endphp
                        <article class="et-v3-review" data-testid="homepage-review">
                            <span aria-hidden="true">“</span>
                            <blockquote>{{ $quote }}</blockquote>
                            @if ($author)<p>{{ $author }}</p>@endif
                            <a href="{{ $review->source_url }}" target="_blank" rel="noopener">{{ $sourceLabel }} <x-icon name="arrow" :size="17" /></a>
                        </article>
                    @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="et-v3-contact" aria-labelledby="home-contact-title">
        <div class="et-container et-v3-contact__grid">
            <div class="et-v3-contact__content">
                <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('contact_kicker_'.$homepageLocale, __('pro.home.contact_kicker')) }}</p>
                <h2 class="et-h2" id="home-contact-title">{{ $storefrontValue('contact_title_'.$homepageLocale, __('pro.home.contact_title')) }}</h2>
                <p class="et-lead et-lead--light">{{ $storefrontValue('contact_text_'.$homepageLocale, __('pro.home.contact_text')) }}</p>
                <a class="et-v3-contact__line" href="tel:{{ $phoneHref }}"><x-icon name="phone" :size="22" /><span><small>{{ __('pro.content.phone') }}</small><strong>{{ $phoneDisplay }}</strong></span></a>
                <a class="et-v3-contact__line" href="mailto:{{ $contactEmail }}"><span class="et-v3-contact__at">@</span><span><small>{{ __('pro.content.email') }}</small><strong>{{ $contactEmail }}</strong></span></a>
                <a class="et-v3-contact__line" href="{{ $mapUrl }}" target="_blank" rel="noopener"><x-icon name="location" :size="22" /><span><small>{{ __('pro.content.address') }}</small><strong>{{ $businessAddress }}</strong><em>{{ __('project.contact.map_pin_note', ['address' => $mapPinAddress]) }}</em></span></a>
                @if ($socialLinks->isNotEmpty())
                    <nav class="et-v3-socials" aria-label="{{ __('pro.home.social_label') }}" data-testid="homepage-social-links">
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener">{{ $social['label'] }} <x-icon name="arrow" :size="15" /></a>
                        @endforeach
                    </nav>
                @endif
                <article class="et-v3-contact__business" data-testid="business-contact-card">
                    <div><small>{{ $storefrontValue('business_kicker_'.$homepageLocale, __('pro.home.business_kicker')) }}</small><strong>{{ $storefrontValue('business_title_'.$homepageLocale, __('project.home.business_title')) }}</strong><p>{{ $storefrontValue('business_text_'.$homepageLocale, __('project.home.business_text')) }}</p></div>
                    <a href="{{ $contactUrl }}">{{ __('project.home.business_link') }} <x-icon name="arrow" :size="16" /></a>
                </article>
                @if ($homepageBusinessPartners->isNotEmpty())
                    <div class="et-v3-partners et-v3-partners--contact" data-testid="homepage-business-partners">
                        <p>{{ __('pro.home.partners_label') }}</p>
                        <div>
                            @foreach ($homepageBusinessPartners as $partner)
                                @php
                                    $partnerAsset = $partner->mediaAsset;
                                    $partnerUrl = $partner->publicWebsiteUrl();
                                    $partnerLogoUrl = \Illuminate\Support\Facades\Storage::disk($partnerAsset->disk)->url($partnerAsset->path);
                                    $partnerAlt = $partnerAsset->getAttribute('alt_'.$homepageLocale) ?: $partner->name;
                                @endphp
                                @if ($partnerUrl)
                                    <a href="{{ $partnerUrl }}" target="_blank" rel="noopener" aria-label="{{ $partner->name }}"><img src="{{ $partnerLogoUrl }}" width="180" height="72" loading="lazy" alt="{{ $partnerAlt }}"></a>
                                @else
                                    <span aria-label="{{ $partner->name }}"><img src="{{ $partnerLogoUrl }}" width="180" height="72" loading="lazy" alt="{{ $partnerAlt }}"></span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="et-map" data-map-card data-testid="map-card">
                @if ($mapEmbedUrl !== null)
                    <iframe class="et-map__frame" src="{{ $mapEmbedUrl }}" title="{{ __('pro.content.map_ready_title') }}" referrerpolicy="no-referrer-when-downgrade" allowfullscreen data-testid="map-iframe"></iframe>
                    <div class="et-map__loaded">
                        <h3 class="et-h3">{{ __('pro.content.map_ready_title') }}</h3>
                        <p>{{ __('pro.content.map_ready_text') }}</p>
                        <a class="et-btn et-btn--primary" href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.content.map_link') }}</a>
                    </div>
                @else
                    <div class="et-map__card">
                        <div class="et-map__pin" aria-hidden="true"><x-icon name="location" :size="24" /></div>
                        <h3 class="et-h3">{{ __('pro.content.map_title') }}</h3>
                        <p>{{ __('pro.content.map_privacy') }}</p>
                        <a class="et-btn et-btn--primary et-btn--full" href="{{ $mapUrl }}" target="_blank" rel="noopener" data-testid="map-external-only">{{ __('pro.content.map_options') }}</a>
                        <a class="et-btn et-btn--link" href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.home.map') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.storefront>
