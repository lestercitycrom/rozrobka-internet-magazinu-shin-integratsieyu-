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
    $hotelVideoAsset = $storefrontSettings?->hotelVideoMedia;
    $hotelVideoUrl = $hotelVideoAsset
        ? \Illuminate\Support\Facades\Storage::disk($hotelVideoAsset->disk)->url($hotelVideoAsset->path)
        : null;
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
    $homepageServiceCollectionsInitialized = false;
    $homepageServiceCollections = collect([
        \App\Modules\Content\Enums\HomepageServiceCollection::Additional->value => collect(),
        \App\Modules\Content\Enums\HomepageServiceCollection::Wheels->value => collect(),
    ]);
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
        if (\Illuminate\Support\Facades\Schema::hasTable('homepage_service_cards')) {
            $homepageServiceCollectionsInitialized = \App\Modules\Content\Models\HomepageServiceCard::query()->withTrashed()->exists();
            foreach (\App\Modules\Content\Enums\HomepageServiceCollection::cases() as $collection) {
                $homepageServiceCollections[$collection->value] = \App\Modules\Content\Models\HomepageServiceCard::query()
                    ->publishedFor($collection)
                    ->with(['servicePage.mediaAssignments.asset', 'mediaAsset'])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            }
        }
    } catch (\Throwable) {
        $homepagePromotions = collect();
        $homepageReviews = collect();
        $homepageRatingSummaries = collect();
        $homepageBusinessPartners = collect();
        $homepagePriceGroups = collect();
        $homepageServiceCollectionsInitialized = false;
        $homepageServiceCollections = collect([
            \App\Modules\Content\Enums\HomepageServiceCollection::Additional->value => collect(),
            \App\Modules\Content\Enums\HomepageServiceCollection::Wheels->value => collect(),
        ]);
    }
    $homepagePromotions = $homepagePromotions->filter(
        fn ($promotion) => filled($promotion->getAttribute('title_'.$homepageLocale))
            && filled($promotion->getAttribute('body_'.$homepageLocale))
            && filled($promotion->getAttribute('cta_label_'.$homepageLocale))
            && \App\Support\PublicEditorialUrl::isValid($promotion->cta_url)
    );
    $homepageServiceCollections = $homepageServiceCollections->map(
        fn ($cards) => $cards->filter(
            fn ($card) => filled($card->getAttribute('title_'.$homepageLocale))
                && filled($card->getAttribute('description_'.$homepageLocale))
                && filled($card->getAttribute('cta_label_'.$homepageLocale))
                && ($card->servicePage !== null || \App\Support\PublicEditorialUrl::isValid($card->cta_url))
        )->values()
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
    $promotionUrl = static fn ($promotion): ?string => \App\Support\PublicEditorialUrl::localized($promotion->cta_url, $homepageLocale);
    $serviceCardUrl = static function ($card) use ($routePrefix, $homepageLocale): ?string {
        $override = \App\Support\PublicEditorialUrl::localized($card->cta_url, $homepageLocale);
        if ($override !== null) {
            return $override;
        }

        $service = $card->servicePage;
        if ($service === null) {
            return null;
        }

        return route(
            $routePrefix.($service->canonical_path ? 'seo-services.show' : 'services.show'),
            $service->canonical_path ?: $service->slug,
        );
    };
    $serviceCardMedia = static function ($card) use ($homepageLocale): ?array {
        $asset = $card->mediaAsset;
        if ($asset === null && $card->servicePage !== null) {
            $asset = $card->servicePage->mediaAssignments
                ->first(fn ($assignment) => $assignment->role === 'hero' && $assignment->asset?->is_active)
                ?->asset;
        }
        if ($asset === null) {
            return null;
        }

        return [
            'url' => \Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path),
            'alt' => $asset->getAttribute('alt_'.$homepageLocale) ?: '',
        ];
    };
@endphp

<x-layouts.storefront :title="__('storefront.site_title')" :description="__('storefront.site_description')">
    <section class="et-v3-hero" data-testid="home-hero">
        <picture>
            <source media="(max-width: 767px)" srcset="{{ $heroMobileImage }}">
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
            <div class="et-v3-logistics-grid">
                <article class="et-v3-logistics-card et-v3-logistics-card--image">
                    <img src="{{ $hotelImage }}" width="1000" height="1200" loading="eager" alt="{{ $hotelImageAlt }}">
                    <div class="et-v3-logistics-card__content">
                        <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('hotel_kicker_'.$homepageLocale, __('pro.home.hotel_kicker')) }}</p>
                        <h3>{{ $storefrontValue('hotel_title_'.$homepageLocale, __('pro.home.hotel_title')) }}</h3>
                        <p>{{ $storefrontValue('hotel_text_'.$homepageLocale, __('pro.home.hotel_text')) }}</p>
                        <a href="{{ $hotelService ? $serviceUrl($hotelService) : route($routePrefix.'services.index') }}">{{ $storefrontValue('hotel_cta_'.$homepageLocale, __('pro.home.hotel_link')) }} <x-icon name="arrow" :size="18" /></a>
                    </div>
                </article>
                <article class="et-v3-logistics-card et-v3-logistics-card--video">
                    <div class="et-v3-logistics-media" data-homepage-video>
                        <img class="et-v3-logistics-media__poster" src="{{ $mobileImage }}" width="1200" height="900" loading="eager" alt="{{ $mobileImageAlt }}">
                        @if ($hotelVideoUrl)
                            <video class="et-v3-logistics-media__video" src="{{ $hotelVideoUrl }}" poster="{{ $mobileImage }}" autoplay muted loop playsinline preload="metadata" aria-label="{{ __('project.home.hotel_video_label') }}" data-homepage-video-element></video>
                            <button class="et-v3-logistics-media__control" type="button" aria-pressed="false" data-homepage-video-control data-pause-label="{{ __('project.home.video_pause') }}" data-play-label="{{ __('project.home.video_play') }}">{{ __('project.home.video_pause') }}</button>
                        @endif
                    </div>
                    <div class="et-v3-logistics-card__content">
                        <p class="et-eyebrow">{{ $storefrontValue('mobile_kicker_'.$homepageLocale, __('pro.home.mobile_kicker')) }}</p>
                        <h3>{{ $storefrontValue('mobile_title_'.$homepageLocale, __('pro.home.mobile_title')) }}</h3>
                        <p>{{ $storefrontValue('mobile_text_'.$homepageLocale, __('pro.home.mobile_text')) }}</p>
                        <button class="et-v3-text-link" type="button" aria-haspopup="dialog" data-dekkhotell-dialog-open>{{ $storefrontValue('mobile_cta_'.$homepageLocale, __('pro.home.mobile_link')) }} <x-icon name="arrow" :size="18" /></button>
                        <noscript>
                            <details class="et-v3-logistics-noscript"><summary>{{ $storefrontValue('mobile_cta_'.$homepageLocale, __('pro.home.mobile_link')) }}</summary><h4>{{ $storefrontValue('mobile_details_title_'.$homepageLocale, __('pro.home.dekkhotell_section_title')) }}</h4><p>{{ $storefrontValue('mobile_details_body_1_'.$homepageLocale, '') }}</p><p>{{ $storefrontValue('mobile_details_body_2_'.$homepageLocale, '') }}</p></details>
                        </noscript>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <dialog class="et-v3-dialog" aria-labelledby="dekkhotell-dialog-title" data-dekkhotell-dialog>
        <div class="et-v3-dialog__surface">
            <button class="et-v3-dialog__close" type="button" aria-label="{{ __('project.home.dialog_close') }}" data-dekkhotell-dialog-close>×</button>
            <p class="et-eyebrow">{{ $storefrontValue('logistics_kicker_'.$homepageLocale, __('pro.home.hotel_kicker')) }}</p>
            <h2 id="dekkhotell-dialog-title">{{ $storefrontValue('mobile_details_title_'.$homepageLocale, __('pro.home.dekkhotell_section_title')) }}</h2>
            <p>{{ $storefrontValue('mobile_details_body_1_'.$homepageLocale, '') }}</p>
            <p>{{ $storefrontValue('mobile_details_body_2_'.$homepageLocale, '') }}</p>
            <a class="et-btn et-btn--primary" href="{{ $hotelService ? $serviceUrl($hotelService) : route($routePrefix.'services.index') }}">{{ $storefrontValue('hotel_cta_'.$homepageLocale, __('pro.home.hotel_link')) }}</a>
        </div>
    </dialog>

    @include('storefront._homepage-promotions')

    @include('storefront._homepage-service-collection', [
        'cards' => $homepageServiceCollections[\App\Modules\Content\Enums\HomepageServiceCollection::Additional->value],
        'collectionKey' => \App\Modules\Content\Enums\HomepageServiceCollection::Additional->value,
        'headingId' => 'home-additional-services-title',
        'testId' => 'homepage-additional-services',
        'kicker' => __('pro.home.additional_services_kicker'),
        'heading' => __('pro.home.additional_services_title'),
        'description' => __('pro.home.additional_services_text'),
    ])

    @if (! $homepageServiceCollectionsInitialized && $homepageServiceCollections->flatten(1)->isEmpty() && $serviceCollection->isNotEmpty())
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
                    @foreach ($serviceCollection->take(6) as $index => $service)
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
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="et-v3-process-strip" aria-labelledby="home-process-title" data-testid="home-process">
        <div class="et-container et-v3-process-strip__inner">
            <div class="et-v3-process-strip__head">
                <p class="et-eyebrow">{{ $storefrontValue('process_kicker_'.$homepageLocale, __('pro.home.process_kicker')) }}</p>
                <h2 class="et-v3-process-strip__title" id="home-process-title">{{ $storefrontValue('process_title_'.$homepageLocale, __('project.home.process_title')) }}</h2>
                <p class="et-v3-process-strip__lead">{{ $storefrontValue('process_lead_'.$homepageLocale, __('project.home.process_note')) }}</p>
            </div>
            <div class="et-v3-process">
                @foreach ([
                    ['calendar-location', $storefrontValue('process_1_title_'.$homepageLocale, __('project.home.process_1_title')), $storefrontValue('process_1_text_'.$homepageLocale, __('project.home.process_1_text'))],
                    ['calendar-check', $storefrontValue('process_2_title_'.$homepageLocale, __('project.home.process_2_title')), $storefrontValue('process_2_text_'.$homepageLocale, __('project.home.process_2_text'))],
                    ['mobile-wrench', $storefrontValue('process_3_title_'.$homepageLocale, __('project.home.process_3_title')), $storefrontValue('process_3_text_'.$homepageLocale, __('project.home.process_3_text'))],
                    ['payment-check', $storefrontValue('process_4_title_'.$homepageLocale, __('project.home.process_4_title')), $storefrontValue('process_4_text_'.$homepageLocale, __('project.home.process_4_text'))],
                ] as $index => [$stepIcon, $stepTitle, $stepText])
                    <article class="et-v3-process__step">
                        <span class="et-v3-process__icon"><x-icon :name="$stepIcon" :size="58" /></span>
                        <small>{{ $index + 1 }}</small>
                        <div><h3>{{ $stepTitle }}</h3><p>{{ $stepText }}</p></div>
                    </article>
                @endforeach
            </div>
            <a class="et-btn et-btn--primary et-v3-process-strip__cta" href="{{ $bookingUrl }}?fulfilment=mobile">{{ $storefrontValue('process_cta_'.$homepageLocale, __('project.home.process_cta')) }} <x-icon name="arrow" :size="18" /></a>
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
                        <strong><small>{{ __('pro.home.price_from') }}</small> {{ number_format($price->price_minor / 100, 0, ',', ' ') }} kr</strong>
                        <x-icon name="arrow" :size="18" />
                    </a>
                @empty
                    <a href="{{ route($routePrefix.'pricing.index') }}"><span>{{ __('pro.home.price_pending') }}</span><strong>{{ __('pro.home.price_all') }}</strong><x-icon name="arrow" :size="18" /></a>
                @endforelse
            </div>
            <a class="et-home-prices__all" href="{{ route($routePrefix.'pricing.index') }}">{{ __('pro.home.price_all') }} <x-icon name="arrow" :size="17" /></a>
        </div>
    </section>

    @include('storefront._homepage-service-collection', [
        'cards' => $homepageServiceCollections[\App\Modules\Content\Enums\HomepageServiceCollection::Wheels->value],
        'collectionKey' => \App\Modules\Content\Enums\HomepageServiceCollection::Wheels->value,
        'headingId' => 'home-wheel-services-title',
        'testId' => 'homepage-wheel-services',
        'kicker' => __('pro.home.wheel_services_kicker'),
        'heading' => __('pro.home.wheel_services_title'),
        'description' => __('pro.home.wheel_services_text'),
    ])

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
        <div class="et-container">
            <div class="et-v3-contact__grid">
              <div class="et-v3-contact__content">
                <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('contact_kicker_'.$homepageLocale, __('pro.home.contact_kicker')) }}</p>
                <h2 class="et-h2" id="home-contact-title">{{ $storefrontValue('contact_title_'.$homepageLocale, __('pro.home.contact_title')) }}</h2>
                <p class="et-lead et-lead--light">{{ $storefrontValue('contact_text_'.$homepageLocale, __('pro.home.contact_text')) }}</p>
                <a class="et-v3-contact__line" href="tel:{{ $phoneHref }}"><x-icon name="phone" :size="22" /><span><small>{{ __('pro.content.phone') }}</small><strong>{{ $phoneDisplay }}</strong></span></a>
                <a class="et-v3-contact__line" href="mailto:{{ $contactEmail }}"><span class="et-v3-contact__at">@</span><span><small>{{ __('pro.content.email') }}</small><strong>{{ $contactEmail }}</strong></span></a>
                <a class="et-v3-contact__line" href="{{ $mapUrl }}" target="_blank" rel="noopener"><x-icon name="location" :size="22" /><span><small>{{ __('pro.content.address') }}</small><strong>{{ $businessAddress }}</strong><em>{{ $storefrontValue('contact_map_note_'.$homepageLocale, __('project.contact.map_pin_note', ['address' => $mapPinAddress])) }}</em></span></a>
                @if ($socialLinks->isNotEmpty())
                    <nav class="et-v3-socials" aria-label="{{ __('pro.home.social_label') }}" data-testid="homepage-social-links">
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener">{{ $social['label'] }} <x-icon name="arrow" :size="15" /></a>
                        @endforeach
                    </nav>
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

            <article class="et-v3-contact__business" data-testid="business-contact-card">
                <div class="et-v3-contact__business-copy">
                    <p class="et-eyebrow et-eyebrow--light">{{ $storefrontValue('business_kicker_'.$homepageLocale, __('pro.home.business_kicker')) }}</p>
                    <h3>{{ $storefrontValue('business_title_'.$homepageLocale, __('project.home.business_title')) }}</h3>
                    <p>{{ $storefrontValue('business_text_'.$homepageLocale, __('project.home.business_text')) }}</p>
                    <a class="et-btn et-btn--primary" href="{{ $contactUrl }}">{{ $storefrontValue('business_cta_'.$homepageLocale, __('project.home.business_link')) }} <x-icon name="arrow" :size="16" /></a>
                </div>
                @if ($homepageBusinessPartners->isNotEmpty())
                    <div class="et-v3-partners et-v3-partners--contact" data-testid="homepage-business-partners">
                        <h4>{{ $storefrontValue('business_partners_heading_'.$homepageLocale, __('pro.home.partners_label')) }}</h4>
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
            </article>
        </div>
    </section>
</x-layouts.storefront>
