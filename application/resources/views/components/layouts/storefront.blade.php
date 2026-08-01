<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @php
        $preview = app()->getLocale() === 'ru';
        $routePrefix = $preview ? 'storefront.preview.' : 'storefront.';
        $cartCount = array_sum(app(\App\Modules\Cart\SessionCart::class)->items());
        $activeSection = match (true) {
            request()->routeIs($routePrefix.'tires.*') => 'tires',
            request()->routeIs($routePrefix.'wheels.*') => 'wheels',
            request()->routeIs($routePrefix.'fitment.*') => 'fitment',
            request()->routeIs($routePrefix.'services.*'), request()->routeIs($routePrefix.'requests.*') => 'services',
            request()->routeIs($routePrefix.'cart.*'), request()->routeIs($routePrefix.'checkout.*'), request()->routeIs($routePrefix.'orders.*') => 'cart',
            default => null,
        };
        $navigationItems = [
            ['section' => 'tires', 'route' => 'tires.index', 'label' => __('storefront.tires')],
            ['section' => 'wheels', 'route' => 'wheels.index', 'label' => __('storefront.wheels')],
            ['section' => 'fitment', 'route' => 'fitment.create', 'label' => __('fitment.nav')],
            ['section' => 'services', 'route' => 'services.index', 'label' => __('storefront.services')],
        ];
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? __('storefront.site_description') }}">
    <meta name="theme-color" content="#c90000">
    <title>{{ $title ?? __('storefront.site_title') }}</title>
    @if ($preview)<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="{{ preg_replace('#/ru(?=/|$)#', '', url()->current(), 1) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">@endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/easytire-icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront min-h-screen bg-easytire-surface font-sans text-easytire-ink antialiased">
    <a href="#innhold" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-slate-950 focus:shadow-lg">{{ __('storefront.skip') }}</a>
    <header class="border-b border-easytire-border bg-easytire-panel">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-2 sm:gap-3 sm:px-8 sm:py-4">
            <a href="{{ route($routePrefix.'home') }}" class="flex min-h-11 shrink-0 items-center gap-2 rounded text-lg font-black tracking-tight text-easytire-ink sm:text-xl" aria-label="{{ __('storefront.home_aria') }}" @if(request()->routeIs($routePrefix.'home')) aria-current="page" @endif>
                <span class="grid size-8 place-items-center sm:size-9" aria-hidden="true"><img src="/brand/easytire-mark.svg" alt="" class="size-8 object-contain sm:size-9"></span><span>EasyTire</span>
            </a>
            <nav aria-label="{{ __('storefront.main_navigation') }}" class="hidden items-center gap-7 text-sm font-semibold text-slate-700 md:flex" data-testid="desktop-navigation">
                @foreach ($navigationItems as $navigationItem)
                    @php($active = $activeSection === $navigationItem['section'])
                    <a href="{{ route($routePrefix.$navigationItem['route']) }}" @class(['inline-flex min-h-11 items-center rounded border-b-2 px-1 transition-colors', 'border-easytire-brand text-easytire-brand-strong' => $active, 'border-transparent hover:border-easytire-brand hover:text-easytire-brand-strong' => ! $active]) @if($active) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                @endforeach
            </nav>
            <a href="{{ route($routePrefix.'tires.index') }}" class="hidden min-h-11 items-center rounded-full bg-easytire-brand px-4 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand lg:inline-flex">{{ __('storefront.find_tires') }}</a>
            <a href="{{ route($routePrefix.'cart.index') }}" @class(['inline-flex min-h-11 shrink-0 items-center rounded border-b-2 px-2 text-sm font-bold transition-colors', 'border-easytire-brand text-easytire-brand-strong' => $activeSection === 'cart', 'border-transparent text-easytire-ink hover:border-easytire-brand hover:text-easytire-brand-strong' => $activeSection !== 'cart']) @if($activeSection === 'cart') aria-current="page" @endif>{{ __('storefront.cart') }} ({{ $cartCount }})</a>
        </div>
        <nav aria-label="{{ __('storefront.quick_links') }}" class="border-t border-easytire-border px-4 md:hidden" data-testid="mobile-navigation">
            <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto text-sm font-semibold text-easytire-muted">
                @foreach ($navigationItems as $navigationItem)
                    @php($active = $activeSection === $navigationItem['section'])
                    <a href="{{ route($routePrefix.$navigationItem['route']) }}" @class(['inline-flex min-h-11 shrink-0 items-center border-b-2 px-3 transition-colors', 'border-easytire-brand text-easytire-brand-strong' => $active, 'border-transparent hover:border-easytire-brand hover:text-easytire-brand-strong' => ! $active]) @if($active) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                @endforeach
            </div>
        </nav>
    </header>
    <main id="innhold">{{ $slot }}</main>
    @php($footerPages = app(\App\Modules\Content\ReadModel\PublishedContentPageReadService::class)->publishedSlugs(['om-oss', 'kontakt', 'personvern', 'kjopsvilkar']))
    <footer class="mt-16 bg-easytire-ink px-5 py-9 text-sm text-white/75 sm:px-8">
        <nav class="mx-auto mb-5 flex max-w-7xl flex-wrap gap-x-5 gap-y-3" aria-label="{{ __('legal.terms') }}">
            @foreach (['om-oss' => 'about', 'kontakt' => 'contact', 'personvern' => 'privacy', 'kjopsvilkar' => 'terms'] as $slug => $label)
                @if (isset($footerPages[$slug]))
                    <a class="rounded underline decoration-white/40 underline-offset-4 hover:text-white focus:outline-2 focus:outline-offset-4 focus:outline-white" href="{{ route($routePrefix.'content.show', $slug) }}">{{ __('legal.'.$label) }}</a>
                @endif
            @endforeach
        </nav>
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 sm:flex-row"><p class="font-semibold text-white">EasyTire · Tromsø</p><p>{{ __('storefront.footer') }}</p></div>
    </footer>
</body>
</html>
