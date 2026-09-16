@props([
    'title' => null,
    'description' => null,
    'activeSection' => null,
    'storeRoute' => null,
    'storeLabel' => null,
    'canonical' => null,
    'image' => null,
    'structuredData' => [],
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @php
        $preview = app()->getLocale() === 'ru';
        $routePrefix = $preview ? 'storefront.preview.' : 'storefront.';
        $storefrontSettings = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('storefront_settings')) {
                $storefrontSettings = app(\App\Modules\Content\ReadModel\StorefrontSettingsReadService::class)->current();
            }
        } catch (\Throwable) {
            $storefrontSettings = null;
        }
        $settingValue = static function (string $field, string $fallback) use ($storefrontSettings): string {
            $value = $storefrontSettings?->getAttribute($field);

            return filled($value) ? (string) $value : $fallback;
        };
        $phoneDisplay = $settingValue('phone_display', (string) config('storefront.phone_display'));
        $phoneHref = $settingValue('phone_href', (string) config('storefront.phone_href'));
        $contactEmail = $settingValue('email', (string) config('storefront.email'));
        $businessAddress = $settingValue('business_address', (string) config('storefront.business_address'));
        $mapUrl = $settingValue('map_url', (string) config('storefront.map_url'));
        $contentPages = [];
        $headerServices = collect();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('content_pages')) {
                $contentPages = app(\App\Modules\Content\ReadModel\PublishedContentPageReadService::class)
                    ->publishedSlugs(['om-oss', 'kontakt', 'personvern', 'kjopsvilkar']);
            }
        } catch (\Throwable) {
            $contentPages = [];
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('service_pages')) {
                $serviceLocale = $preview
                    ? \App\Modules\Content\Enums\ServicePageLocale::Russian
                    : \App\Modules\Content\Enums\ServicePageLocale::NorwegianBokmal;
                $headerServices = collect(
                    app(\App\Modules\Content\ReadModel\PublishedServicePageReadService::class)->list($serviceLocale)
                );
            }
        } catch (\Throwable) {
            $headerServices = collect();
        }

        $seasonalSlugs = [
            'mobilt-dekkskift',
            'dekkverksted',
            'dekkskift',
            'dekkskift-tromso',
            'dekkskift-verksted',
            'hjulskift',
            'sesongskift',
        ];
        $isSeasonalService = static function ($service) use ($seasonalSlugs): bool {
            $persistedFlow = data_get($service, 'requestFlow');
            if ($persistedFlow instanceof \App\Modules\Content\Enums\ServiceRequestFlow) {
                return $persistedFlow === \App\Modules\Content\Enums\ServiceRequestFlow::Seasonal;
            }
            if (is_string($persistedFlow) && in_array($persistedFlow, ['seasonal', 'generic'], true)) {
                return $persistedFlow === 'seasonal';
            }

            return in_array(mb_strtolower((string) data_get($service, 'slug')), $seasonalSlugs, true);
        };
        $seasonalService = $headerServices->first($isSeasonalService);
        $bookingUrl = $seasonalService
            ? route($routePrefix.'requests.create', $seasonalService->slug)
            : route($routePrefix.'services.index');

        try {
            $cartCount = array_sum(app(\App\Modules\Cart\SessionCart::class)->items());
        } catch (\Throwable) {
            $cartCount = null;
        }
        $cartCountLabel = $cartCount ?? __('pro.nav.cart_count_unavailable');
        $cartCountDisplay = $cartCount === null ? '–' : ($cartCount > 99 ? '99+' : (string) $cartCount);

        $contentSlug = request()->route('slug');
        $activeSection = $activeSection ?? match (true) {
            request()->routeIs($routePrefix.'services.*'), request()->routeIs($routePrefix.'requests.*') => 'services',
            request()->routeIs($routePrefix.'tires.*'), request()->routeIs($routePrefix.'wheels.*'),
            request()->routeIs($routePrefix.'fitment.*'), request()->routeIs($routePrefix.'products.*') => 'store',
            request()->routeIs($routePrefix.'cart.*'), request()->routeIs($routePrefix.'checkout.*'),
            request()->routeIs($routePrefix.'orders.*') => 'cart',
            request()->routeIs($routePrefix.'pricing.*') => 'pricing',
            request()->routeIs($routePrefix.'content.show') && $contentSlug === 'om-oss' => 'about',
            request()->routeIs($routePrefix.'content.show') && $contentSlug === 'kontakt' => 'contact',
            default => null,
        };
        $storeHref = route($routePrefix.'store.index');
        $storeNavigationLabel = __('pro.nav.store');
        $servicesHref = route($routePrefix.'services.index');
        $seasonalHref = route($routePrefix.'seo-services.show', 'dekkskift-tromso');
        $hotelHref = route($routePrefix.'seo-services.show', 'dekkhotell-tromso');
        $seasonalRequestRoute = $routePrefix.'requests.create';
        $pricingHref = route($routePrefix.'pricing.index');
        $offersHref = route($routePrefix.'home').'#aktuelle-tilbud';
        $serviceDestination = static function (string $slug, string $fallbackAnchor) use ($headerServices, $routePrefix, $servicesHref): string {
            $service = $headerServices->firstWhere('slug', $slug);
            if ($service) {
                return route(
                    $routePrefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'),
                    $service->canonicalPath ?: $service->slug,
                );
            }

            return $servicesHref.'#'.$fallbackAnchor;
        };
        $isCurrentDestination = static function (string $href): bool {
            $target = parse_url($href);
            if (($target['path'] ?? '/') !== request()->getPathInfo() || isset($target['fragment'])) {
                return false;
            }
            parse_str($target['query'] ?? '', $requiredQuery);

            return collect($requiredQuery)->every(
                static fn ($value, $key): bool => (string) request()->query((string) $key) === (string) $value,
            );
        };
        $serviceGroups = [
            [
                'label' => __('pro.nav.tyres_wheels_group'),
                'children' => [
                    ['href' => route($seasonalRequestRoute, ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile']), 'label' => __('pro.nav.home_service')],
                    ['href' => route($seasonalRequestRoute, ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop']), 'label' => __('pro.nav.workshop_service')],
                    ['href' => $hotelHref, 'label' => __('pro.nav.hotel')],
                    ['href' => $serviceDestination('omlegging-balansering', 'service-mounting-balancing'), 'label' => __('pro.nav.mounting_balancing')],
                    ['href' => $serviceDestination('dekkreparasjon', 'service-puncture-repair'), 'label' => __('pro.nav.puncture_repair')],
                    ['href' => $serviceDestination('tpms-sensor', 'service-tpms'), 'label' => __('pro.nav.tpms')],
                    ['href' => $serviceDestination('tesla-og-elbil', 'service-tesla-ev'), 'label' => __('pro.nav.tesla_ev')],
                    ['href' => $serviceDestination('bobil-og-varebil', 'service-motorhome-van'), 'label' => __('pro.nav.motorhome_van')],
                ],
            ],
            [
                'label' => __('pro.nav.car_services_group'),
                'children' => [
                    ['href' => $serviceDestination('oljeskift', 'service-oil-change'), 'label' => __('pro.nav.oil_change')],
                    ['href' => $serviceDestination('interiorrens', 'service-interior-cleaning'), 'label' => __('pro.nav.interior_cleaning')],
                    ['href' => $serviceDestination('polering-lakkorrigering', 'service-polishing-paint'), 'label' => __('pro.nav.polishing_paint')],
                    ['href' => $serviceDestination('bilbatteri', 'service-battery'), 'label' => __('pro.nav.battery')],
                    ['href' => $serviceDestination('lyspaerer-viskerblad', 'service-lights-wipers'), 'label' => __('pro.nav.lights_wipers')],
                    ['href' => $serviceDestination('utelasing-bil', 'service-lockout'), 'label' => __('pro.nav.lockout')],
                ],
            ],
        ];
        $serviceGroups = collect($serviceGroups)->map(static function (array $group) use ($isCurrentDestination): array {
            $group['children'] = collect($group['children'])->map(static function (array $child) use ($isCurrentDestination): array {
                $child['active'] = $isCurrentDestination($child['href']);

                return $child;
            })->all();

            return $group;
        })->all();
        $navigationItems = [
            [
                'section' => 'services',
                'href' => $servicesHref,
                'label' => __('pro.nav.services'),
                'groups' => $serviceGroups,
            ],
            ['section' => 'store', 'href' => $storeHref, 'label' => $storeNavigationLabel],
            ['section' => 'pricing', 'href' => $pricingHref, 'label' => __('pro.nav.prices')],
            ['section' => 'offers', 'href' => $offersHref, 'label' => __('pro.nav.offers')],
        ];
        if (isset($contentPages['om-oss'])) {
            $navigationItems[] = ['section' => 'about', 'href' => route($routePrefix.'content.show', 'om-oss'), 'label' => __('pro.nav.about')];
        }
        if (isset($contentPages['kontakt'])) {
            $navigationItems[] = ['section' => 'contact', 'href' => route($routePrefix.'content.show', 'kontakt'), 'label' => __('pro.nav.contact')];
        }
        $canonicalUrl = $canonical ?: ($preview ? preg_replace('#/ru(?=/|$)#', '', url()->current(), 1) : url()->current());
        $metaTitle = $title ?? __('storefront.site_title');
        $metaDescription = $description ?? __('storefront.site_description');
        $socialImage = $image ?: url('/brand/exterior.webp');
        if (is_string($socialImage) && str_starts_with($socialImage, '/')) $socialImage = url($socialImage);
        $schemas = [[
            '@context' => 'https://schema.org',
            '@type' => 'AutoRepair',
            '@id' => url('/').'#business',
            'name' => 'EasyTire',
            'url' => url('/'),
            'telephone' => $phoneDisplay,
            'email' => $contactEmail,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $businessAddress,
                'addressLocality' => 'Tromsø',
                'postalCode' => '9018',
                'addressCountry' => 'NO',
            ],
            'image' => $socialImage,
        ], ...array_values(is_array($structuredData) ? $structuredData : [])];
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="theme-color" content="#101010">
    <title>{{ $metaTitle }}</title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="EasyTire">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($preview)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-96x96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource/barlow/files/barlow-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource/barlow/files/barlow-latin-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource/barlow/files/barlow-latin-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @foreach ($schemas as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront" data-storefront-style="sharp">
    @if ($preview)
        <div class="et-demo-banner"><strong>QA</strong><span>{{ __('pro.qa_preview') }}</span></div>
    @endif

    <a class="skip-link" href="#innhold">{{ __('storefront.skip') }}</a>

    <div class="et-topbar">
        <div class="et-container et-topbar__inner">
            <div class="et-topbar__list">
                <span>{{ __('pro.topbar_local') }}</span>
                <span>{{ __('pro.topbar_service') }}</span>
            </div>
            <div class="et-topbar__list">
                <a href="tel:{{ $phoneHref }}">{{ $phoneDisplay }}</a>
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.find_workshop') }}</a>
            </div>
        </div>
    </div>

    <header class="et-header" data-site-header>
        <div class="et-container et-header__inner">
            <a class="et-logo min-h-11" href="{{ route($routePrefix.'home') }}" aria-label="{{ __('storefront.home_aria') }}" @if(request()->routeIs($routePrefix.'home')) aria-current="page" @endif>
                <img src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire">
            </a>

            <nav class="et-nav" aria-label="{{ __('storefront.main_navigation') }}" data-testid="desktop-navigation">
                @foreach ($navigationItems as $navigationItem)
                    @if (! empty($navigationItem['groups']))
                        <div class="et-nav__item et-nav__item--mega" data-mega-menu>
                            <span class="et-nav__trigger">
                                <a class="min-h-11" href="{{ $navigationItem['href'] }}" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                                <button type="button" aria-label="{{ $navigationItem['label'] }}" aria-expanded="false" aria-controls="services-mega-menu" data-mega-menu-toggle><span aria-hidden="true"></span></button>
                            </span>
                            <div class="et-nav__submenu et-nav__submenu--mega" id="services-mega-menu" aria-label="{{ $navigationItem['label'] }}" aria-hidden="true" data-mega-menu-panel>
                                <a class="et-nav__all-services min-h-11" href="{{ $navigationItem['href'] }}">{{ __('pro.nav.all_services') }} <x-icon name="arrow" :size="17" /></a>
                                <div class="et-nav__mega-grid">
                                    @foreach ($navigationItem['groups'] as $group)
                                        <section aria-labelledby="services-mega-group-{{ $loop->index }}">
                                            <h2 id="services-mega-group-{{ $loop->index }}">{{ $group['label'] }}</h2>
                                            <ul>
                                                @foreach ($group['children'] as $child)
                                                    <li><a class="min-h-11" href="{{ $child['href'] }}" @if($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a class="min-h-11" href="{{ $navigationItem['href'] }}" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                    @endif
                @endforeach
            </nav>

            <div class="et-header__actions">
                <div class="et-catalog-search" data-catalog-search>
                    <button class="et-catalog-search__toggle min-h-11" type="button" aria-label="{{ __('pro.nav.catalog_search_open') }}" aria-expanded="false" aria-controls="header-catalog-search" data-catalog-search-toggle><x-icon name="search" :size="20" /></button>
                    <div class="et-catalog-search__panel" id="header-catalog-search" aria-hidden="true" data-catalog-search-panel>
                        <form method="GET" action="{{ route($routePrefix.'tires.index') }}">
                            <label for="header-catalog-query">{{ __('pro.nav.catalog_search') }}</label>
                            <div>
                                <input id="header-catalog-query" name="q" type="search" autocomplete="off" placeholder="{{ __('pro.nav.catalog_search_placeholder') }}" list="header-catalog-suggestions" aria-controls="header-catalog-suggestions" data-catalog-autocomplete="{{ route($routePrefix.'tires.suggestions') }}">
                                <button type="submit">{{ __('pro.nav.catalog_search_submit') }}</button>
                            </div>
                            <datalist id="header-catalog-suggestions"></datalist>
                        </form>
                    </div>
                </div>
                <a class="et-btn et-btn--primary min-h-11" href="tel:{{ $phoneHref }}" data-testid="header-call"><x-icon name="phone" :size="17" />{{ __('pro.nav.call') }}</a>
                <a class="et-cart min-h-11" href="{{ route($routePrefix.'cart.index') }}" aria-label="{{ __('pro.nav.cart') }}: {{ $cartCountLabel }}" @if($activeSection === 'cart') aria-current="page" @endif data-testid="header-cart">
                    <x-icon name="cart" :size="22" />
                    <span class="et-cart__count" aria-hidden="true">{{ $cartCountDisplay }}</span>
                </a>
                <button class="et-menu-button min-h-11" type="button" aria-label="{{ __('pro.menu_open') }}" aria-expanded="false" data-menu-toggle><span></span></button>
            </div>
        </div>

        <div class="et-mobile-menu" data-mobile-menu>
            <nav class="et-container" aria-label="{{ __('storefront.quick_links') }}" data-testid="mobile-navigation">
                @foreach ($navigationItems as $navigationItem)
                    @if (! empty($navigationItem['groups']))
                        <details class="et-mobile-menu__group">
                            <summary class="min-h-11" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</summary>
                            <div class="et-mobile-menu__children">
                                <a class="min-h-11" href="{{ $navigationItem['href'] }}">{{ __('pro.nav.all_services') }}</a>
                                @foreach ($navigationItem['groups'] as $group)
                                    <p>{{ $group['label'] }}</p>
                                    @foreach ($group['children'] as $child)
                                        <a class="min-h-11" href="{{ $child['href'] }}" @if($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                    @endforeach
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a class="min-h-11" href="{{ $navigationItem['href'] }}" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                    @endif
                @endforeach
                <form class="et-mobile-menu__search" method="GET" action="{{ route($routePrefix.'tires.index') }}">
                    <label for="mobile-catalog-query">{{ __('pro.nav.catalog_search') }}</label>
                    <div><input id="mobile-catalog-query" name="q" type="search" autocomplete="off" placeholder="{{ __('pro.nav.catalog_search_placeholder') }}" list="mobile-catalog-suggestions" aria-controls="mobile-catalog-suggestions" data-catalog-autocomplete="{{ route($routePrefix.'tires.suggestions') }}"><button type="submit" aria-label="{{ __('pro.nav.catalog_search_submit') }}"><x-icon name="search" :size="19" /></button></div>
                    <datalist id="mobile-catalog-suggestions"></datalist>
                </form>
                <a class="min-h-11" href="tel:{{ $phoneHref }}" data-testid="mobile-call-link">{{ __('pro.nav.call') }}</a>
                <a class="min-h-11" href="{{ route($routePrefix.'cart.index') }}" @if($activeSection === 'cart') aria-current="page" @endif data-testid="mobile-cart-link">{{ __('pro.nav.cart') }} ({{ $cartCountLabel }})</a>
            </nav>
        </div>
    </header>

    <main class="et-page-main" id="innhold">{{ $slot }}</main>

    <footer class="et-footer" data-footer-groups>
        <div class="et-container et-footer__main">
            <div class="et-footer__brand">
                <div class="et-footer__logo-plate">
                    <img class="et-footer__logo" src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire">
                </div>
                <p>{{ __('pro.footer.text') }}</p>
                <div class="et-footer__contact">
                    <a href="tel:{{ $phoneHref }}">{{ $phoneDisplay }}</a>
                    <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ $businessAddress }}</a>
                    <span>EasyTask AS · Org.nr. 934 860 187 MVA</span>
                </div>
                <a class="et-btn et-btn--ghost et-btn--small" href="{{ $bookingUrl }}" data-testid="footer-booking">{{ __('pro.nav.booking') }}</a>
            </div>

            <div class="et-footer__nav" data-footer-group>
                <button class="et-footer__toggle" type="button" aria-expanded="true" aria-controls="footer-services-links" data-footer-toggle data-testid="footer-services-toggle">{{ __('pro.footer.services') }}</button>
                <h2>{{ __('pro.footer.services') }}</h2>
                <ul id="footer-services-links" data-footer-links>
                    <li><a href="{{ route($seasonalRequestRoute, ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'mobile']) }}">{{ __('pro.nav.home_service') }}</a></li>
                    <li><a href="{{ $hotelHref }}">{{ __('pro.nav.hotel') }}</a></li>
                    <li><a href="{{ route($seasonalRequestRoute, ['slug' => 'mobilt-dekkskift', 'fulfilment' => 'workshop']) }}">{{ __('pro.nav.workshop_service') }}</a></li>
                    <li><a href="{{ $serviceDestination('oljeskift', 'service-oil-change') }}">{{ __('pro.nav.oil_change') }}</a></li>
                    <li><a href="{{ $servicesHref }}#bilpleie">{{ __('pro.footer.car_care') }}</a></li>
                    <li><a href="{{ $serviceDestination('tesla-og-elbil', 'service-tesla-ev') }}">{{ __('pro.nav.tesla_ev') }}</a></li>
                    <li><a href="{{ $servicesHref }}">{{ __('pro.footer.all_services') }}</a></li>
                    <li><a href="{{ $pricingHref }}">{{ __('pro.nav.prices') }}</a></li>
                </ul>
            </div>

            <div class="et-footer__nav" data-footer-group>
                <button class="et-footer__toggle" type="button" aria-expanded="true" aria-controls="footer-store-links" data-footer-toggle data-testid="footer-store-toggle">{{ __('pro.footer.store') }}</button>
                <h2>{{ __('pro.footer.store') }}</h2>
                <ul id="footer-store-links" data-footer-links>
                    <li><a href="{{ route($routePrefix.'store.index') }}">{{ __('pro.footer.find_car') }}</a></li>
                    <li><a href="{{ route($routePrefix.'tires.index') }}">{{ __('pro.footer.tires') }}</a></li>
                    <li><a href="{{ route($routePrefix.'wheels.index') }}">{{ __('pro.footer.wheels') }}</a></li>
                    <li><a href="{{ route($routePrefix.'requests.create', ['slug' => 'dekk-felg-og-hjulpakker']) }}">{{ __('pro.footer.packages') }}</a></li>
                    <li><a href="{{ route($routePrefix.'cart.index') }}">{{ __('pro.nav.cart') }}</a></li>
                </ul>
            </div>

            <div class="et-footer__nav et-footer__nav--company" data-footer-group>
                <button class="et-footer__toggle" type="button" aria-expanded="true" aria-controls="footer-company-links" data-footer-toggle data-testid="footer-company-toggle">{{ __('pro.footer.company') }}</button>
                <h2>{{ __('pro.footer.company') }}</h2>
                <ul id="footer-company-links" data-footer-links>
                    @foreach (['om-oss' => 'about', 'kontakt' => 'contact', 'personvern' => 'privacy', 'kjopsvilkar' => 'terms'] as $slug => $label)
                        @if (isset($contentPages[$slug]))
                            <li><a href="{{ route($routePrefix.'content.show', $slug) }}">{{ __('legal.'.$label) }}</a></li>
                        @endif
                    @endforeach
                    <li><a href="/dokumenter/angreskjema-easytire.pdf">{{ __('pro.footer.withdrawal') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="et-container et-footer__bottom">
            <span>{{ __('pro.footer.copyright', ['year' => now()->year]) }}</span>
        </div>
    </footer>
</body>
</html>
