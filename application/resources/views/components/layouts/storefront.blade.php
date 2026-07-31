<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @php($preview = app()->getLocale() === 'ru')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? __('storefront.site_description') }}">
    <meta name="theme-color" content="#e10600">
    <title>{{ $title ?? __('storefront.site_title') }}</title>
    @if ($preview)<meta name="robots" content="noindex,nofollow"><link rel="canonical" href="{{ preg_replace('#/ru(?=/|$)#', '', url()->current(), 1) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}">@endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/easytire-icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront min-h-screen bg-easytire-surface font-sans text-easytire-ink antialiased">
    @php($routePrefix = $preview ? 'storefront.preview.' : 'storefront.')
    @php($cartCount = array_sum(app(\App\Modules\Cart\SessionCart::class)->items()))
    <a href="#innhold" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-slate-950 focus:shadow-lg">{{ __('storefront.skip') }}</a>
    <header class="border-b border-easytire-border bg-easytire-panel">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-8">
            <a href="{{ route($routePrefix.'home') }}" class="flex items-center gap-2 text-xl font-black tracking-tight text-easytire-ink" aria-label="{{ __('storefront.home_aria') }}">
                <span class="grid size-9 place-items-center" aria-hidden="true"><img src="/brand/easytire-mark.svg" alt="" class="size-9 object-contain"></span><span>EasyTire</span>
            </a>
            <nav aria-label="{{ __('storefront.main_navigation') }}" class="hidden items-center gap-7 text-sm font-semibold text-slate-700 md:flex" data-testid="desktop-navigation">
                <a href="{{ route($routePrefix.'tires.index') }}" class="rounded hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">{{ __('storefront.tires') }}</a><a href="{{ route($routePrefix.'wheels.index') }}" class="rounded hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">{{ __('storefront.wheels') }}</a><a href="{{ route($routePrefix.'services.index') }}" class="rounded hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">{{ __('storefront.services') }}</a>
            </nav>
            <a href="{{ route($routePrefix.'tires.index') }}" class="rounded-full bg-easytire-brand px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">{{ __('storefront.find_tires') }}</a>
            <a href="{{ route($routePrefix.'cart.index') }}" class="min-h-11 rounded px-2 py-2 text-sm font-bold text-easytire-ink">{{ __('storefront.cart') }} ({{ $cartCount }})</a>
        </div>
        <nav aria-label="{{ __('storefront.quick_links') }}" class="border-t border-easytire-border px-5 py-3 md:hidden" data-testid="mobile-navigation">
            <div class="mx-auto flex max-w-7xl gap-5 overflow-x-auto text-sm font-semibold text-easytire-muted">
                <a href="{{ route($routePrefix.'tires.index') }}" class="shrink-0">{{ __('storefront.tires') }}</a><a href="{{ route($routePrefix.'wheels.index') }}" class="shrink-0">{{ __('storefront.wheels') }}</a><a href="{{ route($routePrefix.'home') }}#tjenester" class="shrink-0">{{ __('storefront.services') }}</a>
            </div>
        </nav>
    </header>
    <main id="innhold">{{ $slot }}</main>
    <footer class="mt-16 bg-easytire-ink px-5 py-9 text-sm text-white/75 sm:px-8">
        <div class="mx-auto flex max-w-7xl flex-col justify-between gap-2 sm:flex-row"><p class="font-semibold text-white">EasyTire · Tromsø</p><p>{{ __('storefront.footer') }}</p></div>
    </footer>
</body>
</html>
