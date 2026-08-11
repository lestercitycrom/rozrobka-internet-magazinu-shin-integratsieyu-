@php
    $navigationGroups = [
        __('pro.admin.workspace') => [
            ['key' => 'dashboard', 'route' => 'dashboard', 'patterns' => ['admin.dashboard'], 'icon' => '⌂'],
            ['key' => 'service_requests', 'route' => 'service-requests.index', 'patterns' => ['admin.service-requests.*'], 'icon' => '✦'],
            ['key' => 'orders', 'route' => 'orders.index', 'patterns' => ['admin.orders.*'], 'icon' => '⇄'],
            ['key' => 'catalog', 'route' => 'catalog.index', 'patterns' => ['admin.catalog.*'], 'icon' => '▦'],
            ['key' => 'imports', 'route' => 'imports.index', 'patterns' => ['admin.imports.*'], 'icon' => '↻'],
        ],
        __('pro.admin.system') => [
            ['key' => 'services', 'route' => 'services.index', 'patterns' => ['admin.services.*'], 'icon' => '¶'],
            ['key' => 'service_addons', 'route' => 'service-addons.index', 'patterns' => ['admin.service-addons.*'], 'icon' => '+'],
            ['key' => 'content', 'route' => 'content-pages.index', 'patterns' => ['admin.content-pages.*'], 'icon' => 'T'],
            ['key' => 'homepage_promotions', 'route' => 'homepage-promotions.index', 'patterns' => ['admin.homepage-promotions.*'], 'icon' => '★'],
            ['key' => 'homepage_reviews', 'route' => 'homepage-reviews.index', 'patterns' => ['admin.homepage-reviews.*'], 'icon' => '✓'],
            ['key' => 'media', 'route' => 'media.index', 'patterns' => ['admin.media.*'], 'icon' => '▧'],
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
