@props([
    'title' => null,
    'description' => null,
    'activeSection' => null,
    'storeRoute' => null,
    'storeLabel' => null,
    'canonical' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @php
        $preview = app()->getLocale() === 'ru';
        $routePrefix = $preview ? 'storefront.preview.' : 'storefront.';
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
            $cartCount = 0;
        }

        $contentSlug = request()->route('slug');
        $activeSection = $activeSection ?? match (true) {
            request()->routeIs($routePrefix.'services.*'), request()->routeIs($routePrefix.'requests.*') => 'services',
            request()->routeIs($routePrefix.'tires.*'), request()->routeIs($routePrefix.'wheels.*'),
            request()->routeIs($routePrefix.'fitment.*'), request()->routeIs($routePrefix.'products.*') => 'store',
            request()->routeIs($routePrefix.'cart.*'), request()->routeIs($routePrefix.'checkout.*'),
            request()->routeIs($routePrefix.'orders.*') => 'cart',
            request()->routeIs($routePrefix.'content.show') && $contentSlug === 'om-oss' => 'about',
            request()->routeIs($routePrefix.'content.show') && $contentSlug === 'kontakt' => 'contact',
            default => null,
        };
        $storeHref = $storeRoute ?: route($routePrefix.'store.index');
        $storeNavigationLabel = $storeLabel ?: __('pro.nav.store');
        $navigationItems = [
            ['section' => 'services', 'href' => route($routePrefix.'services.index'), 'label' => __('pro.nav.services')],
            ['section' => 'store', 'href' => $storeHref, 'label' => $storeNavigationLabel],
        ];
        if (isset($contentPages['om-oss'])) {
            $navigationItems[] = ['section' => 'about', 'href' => route($routePrefix.'content.show', 'om-oss'), 'label' => __('pro.nav.about')];
        }
        if (isset($contentPages['kontakt'])) {
            $navigationItems[] = ['section' => 'contact', 'href' => route($routePrefix.'content.show', 'kontakt'), 'label' => __('pro.nav.contact')];
        }
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? __('storefront.site_description') }}">
    <meta name="theme-color" content="#101010">
    <title>{{ $title ?? __('storefront.site_title') }}</title>
    @if ($preview)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="canonical" href="{{ $canonical ?: ($preview ? preg_replace('#/ru(?=/|$)#', '', url()->current(), 1) : url()->current()) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/easytire-icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront">
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
                <a href="tel:+4793958270">+47 939 58 270</a>
                <a href="https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic" target="_blank" rel="noopener">{{ __('pro.find_workshop') }}</a>
            </div>
        </div>
    </div>

    <header class="et-header">
        <div class="et-container et-header__inner">
            <a class="et-logo min-h-11" href="{{ route($routePrefix.'home') }}" aria-label="{{ __('storefront.home_aria') }}" @if(request()->routeIs($routePrefix.'home')) aria-current="page" @endif>
                <img src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire">
            </a>

            <nav class="et-nav" aria-label="{{ __('storefront.main_navigation') }}" data-testid="desktop-navigation">
                @foreach ($navigationItems as $navigationItem)
                    <a class="min-h-11" href="{{ $navigationItem['href'] }}" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                @endforeach
            </nav>

            <div class="et-header__actions">
                <a class="et-btn et-btn--primary min-h-11" href="{{ $bookingUrl }}" data-testid="header-booking">{{ __('pro.nav.booking') }}</a>
                <a class="et-cart min-h-11" href="{{ route($routePrefix.'cart.index') }}" aria-label="{{ __('pro.nav.cart') }}: {{ $cartCount }}" @if($activeSection === 'cart') aria-current="page" @endif data-testid="header-cart">
                    <x-icon name="cart" :size="22" />
                    <span class="et-cart__count">{{ $cartCount }}</span>
                </a>
                <button class="et-menu-button min-h-11" type="button" aria-label="{{ __('pro.menu_open') }}" aria-expanded="false" data-menu-toggle><span></span></button>
            </div>
        </div>

        <div class="et-mobile-menu" data-mobile-menu>
            <nav class="et-container" aria-label="{{ __('storefront.quick_links') }}" data-testid="mobile-navigation">
                @foreach ($navigationItems as $navigationItem)
                    <a class="min-h-11" href="{{ $navigationItem['href'] }}" @if($activeSection === $navigationItem['section']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                @endforeach
                <a class="min-h-11" href="{{ $bookingUrl }}" data-testid="mobile-booking-link">{{ __('pro.nav.booking') }}</a>
                <a class="min-h-11" href="{{ route($routePrefix.'cart.index') }}" @if($activeSection === 'cart') aria-current="page" @endif data-testid="mobile-cart-link">{{ __('pro.nav.cart') }} ({{ $cartCount }})</a>
            </nav>
        </div>
    </header>

    <main class="et-page-main" id="innhold">{{ $slot }}</main>

    <footer class="et-footer">
        <div class="et-container et-footer__main">
            <div class="et-footer__brand">
                <img class="et-footer__logo" src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire">
                <p>{{ __('pro.footer.text') }}</p>
                <div class="et-footer__contact">
                    <a href="tel:+4793958270">+47 939 58 270</a>
                    <a href="mailto:max.easytire@gmail.com">max.easytire@gmail.com</a>
                </div>
                <a class="et-btn et-btn--ghost et-btn--small" href="{{ $bookingUrl }}" data-testid="footer-booking">{{ __('pro.nav.booking') }}</a>
            </div>

            <div>
                <h2>{{ __('pro.footer.services') }}</h2>
                <ul>
                    @foreach ($headerServices->take(3) as $service)
                        <li><a href="{{ route($routePrefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ $service->title }}</a></li>
                    @endforeach
                    <li><a href="{{ route($routePrefix.'services.index') }}">{{ __('pro.footer.all_services') }}</a></li>
                    <li><a href="{{ route($routePrefix.'pricing.index') }}">{{ __('public_pricing.kicker') }}</a></li>
                </ul>
            </div>

            <div>
                <h2>{{ __('pro.footer.store') }}</h2>
                <ul>
                    <li><a href="{{ route($routePrefix.'store.index') }}">{{ __('pro.footer.find_car') }}</a></li>
                    <li><a href="{{ route($routePrefix.'tires.index') }}">{{ __('pro.footer.tires') }}</a></li>
                    <li><a href="{{ route($routePrefix.'wheels.index') }}">{{ __('pro.footer.wheels') }}</a></li>
                    <li><a href="{{ route($routePrefix.'cart.index') }}">{{ __('pro.nav.cart') }}</a></li>
                </ul>
            </div>

            <div>
                <h2>{{ __('pro.footer.company') }}</h2>
                <ul>
                    @foreach (['om-oss' => 'about', 'kontakt' => 'contact', 'personvern' => 'privacy', 'kjopsvilkar' => 'terms'] as $slug => $label)
                        @if (isset($contentPages[$slug]))
                            <li><a href="{{ route($routePrefix.'content.show', $slug) }}">{{ __('legal.'.$label) }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="et-container et-footer__bottom">
            <span>{{ __('pro.footer.copyright', ['year' => now()->year]) }}</span>
            <span>{{ __('pro.footer.locale_note') }}</span>
        </div>
    </footer>
</body>
</html>
