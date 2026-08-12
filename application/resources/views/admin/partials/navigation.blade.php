@php
    $navigationGroups = [
        __('pro.admin.workspace') => [
            ['key' => 'dashboard', 'route' => 'dashboard', 'patterns' => ['admin.dashboard'], 'icon' => '⌂'],
            ['key' => 'service_requests', 'route' => 'service-requests.index', 'patterns' => ['admin.service-requests.*'], 'icon' => '✦'],
            ['key' => 'booking_calendar', 'route' => 'booking-calendar.index', 'patterns' => ['admin.booking-calendar.*'], 'icon' => '31'],
            ['key' => 'orders', 'route' => 'orders.index', 'patterns' => ['admin.orders.*'], 'icon' => '⇄'],
            ['key' => 'catalog', 'route' => 'catalog.index', 'patterns' => ['admin.catalog.*'], 'icon' => '▦'],
            ['key' => 'imports', 'route' => 'imports.index', 'patterns' => ['admin.imports.*'], 'icon' => '↻'],
        ],
        __('pro.admin.system') => [
            ['key' => 'services', 'route' => 'services.index', 'patterns' => ['admin.services.*'], 'icon' => '¶'],
            ['key' => 'service_addons', 'route' => 'service-addons.index', 'patterns' => ['admin.service-addons.*'], 'icon' => '+'],
            ['key' => 'public_service_prices', 'route' => 'public-service-prices.index', 'patterns' => ['admin.public-service-prices.*'], 'icon' => 'kr'],
            ['key' => 'content', 'route' => 'content-pages.index', 'patterns' => ['admin.content-pages.*'], 'icon' => 'T'],
            ['key' => 'homepage_promotions', 'route' => 'homepage-promotions.index', 'patterns' => ['admin.homepage-promotions.*'], 'icon' => '★'],
            ['key' => 'homepage_reviews', 'route' => 'homepage-reviews.index', 'patterns' => ['admin.homepage-reviews.*'], 'icon' => '✓'],
            ['key' => 'homepage_ratings', 'route' => 'homepage-rating-summaries.index', 'patterns' => ['admin.homepage-rating-summaries.*'], 'icon' => '5'],
            ['key' => 'homepage_partners', 'route' => 'homepage-business-partners.index', 'patterns' => ['admin.homepage-business-partners.*'], 'icon' => 'B2B'],
            ['key' => 'media', 'route' => 'media.index', 'patterns' => ['admin.media.*'], 'icon' => '▧'],
            ['key' => 'storefront_settings', 'route' => 'storefront-settings.edit', 'patterns' => ['admin.storefront-settings.*'], 'icon' => 'S'],
            ['key' => 'pricing', 'route' => 'pricing-rules.index', 'patterns' => ['admin.pricing-rules.*'], 'icon' => '%'],
            ['key' => 'health', 'route' => 'health', 'patterns' => ['admin.health'], 'icon' => '●'],
        ],
    ];
@endphp

<div class="et-admin-sidebar__brand">
    <a href="{{ route('admin.dashboard') }}" aria-label="{{ __('admin.brand') }}">
        <img src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire Admin">
    </a>
</div>

<nav class="et-admin-nav" aria-label="{{ __('admin.topbar.menu') }}">
    @foreach ($navigationGroups as $groupLabel => $items)
        <div class="et-admin-nav__label">{{ $groupLabel }}</div>
        @foreach ($items as $item)
            @php($active = collect($item['patterns'])->contains(fn ($pattern) => request()->routeIs($pattern)))
            <a href="{{ route('admin.'.$item['route']) }}" @if($active) aria-current="page" @endif>
                <span class="et-admin-nav__icon" aria-hidden="true">{{ $item['icon'] }}</span>
                <span>{{ __('admin_nav.'.$item['key']) }}</span>
            </a>
        @endforeach
    @endforeach
</nav>

<div class="et-admin-sidebar__footer">{{ __('pro.admin.guardrail') }}</div>
