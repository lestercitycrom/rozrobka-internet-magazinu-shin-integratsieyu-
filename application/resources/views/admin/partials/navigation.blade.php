@php
    $adminUser = auth()->user();
    $workspace = [
        ['key' => 'dashboard', 'route' => 'dashboard', 'patterns' => ['admin.dashboard']],
    ];
    if ($adminUser->isOwner() || $adminUser->role === \App\Modules\Identity\Enums\UserRole::Operations) {
        $workspace = [...$workspace,
            ['key' => 'clients', 'route' => 'clients.index', 'patterns' => ['admin.clients.*']],
            ['key' => 'service_requests', 'route' => 'service-requests.index', 'patterns' => ['admin.service-requests.*']],
            ['key' => 'booking_calendar', 'route' => 'booking-calendar.index', 'patterns' => ['admin.booking-calendar.index']],
        ];
    }
    if ($adminUser->isOwner()) {
        $workspace = [...$workspace,
            ['key' => 'orders', 'route' => 'orders.index', 'patterns' => ['admin.orders.*']],
            ['key' => 'catalog', 'route' => 'catalog.index', 'patterns' => ['admin.catalog.*']],
            ['key' => 'imports', 'route' => 'imports.index', 'patterns' => ['admin.imports.*']],
        ];
    }

    $system = [];
    if ($adminUser->canManageContent()) {
        $system = [
            ['key' => 'services', 'route' => 'services.index', 'patterns' => ['admin.services.*']],
            ['key' => 'content', 'route' => 'content-pages.index', 'patterns' => ['admin.content-pages.*']],
            ['key' => 'homepage_promotions', 'route' => 'homepage-promotions.index', 'patterns' => ['admin.homepage-promotions.*']],
            ['key' => 'homepage_service_collections', 'route' => 'homepage-service-collections.index', 'parameters' => ['collection' => 'additional'], 'patterns' => ['admin.homepage-service-collections.*']],
            ['key' => 'homepage_reviews', 'route' => 'homepage-reviews.index', 'patterns' => ['admin.homepage-reviews.*']],
            ['key' => 'homepage_ratings', 'route' => 'homepage-rating-summaries.index', 'patterns' => ['admin.homepage-rating-summaries.*']],
            ['key' => 'homepage_partners', 'route' => 'homepage-business-partners.index', 'patterns' => ['admin.homepage-business-partners.*']],
            ['key' => 'media', 'route' => 'media.index', 'patterns' => ['admin.media.*']],
        ];
    }
    if ($adminUser->isOwner()) {
        $system = [...$system,
            ['key' => 'service_addons', 'route' => 'service-addons.index', 'patterns' => ['admin.service-addons.*']],
            ['key' => 'public_service_prices', 'route' => 'public-service-prices.index', 'patterns' => ['admin.public-service-prices.*']],
            ['key' => 'storefront_settings', 'route' => 'storefront-settings.edit', 'patterns' => ['admin.storefront-settings.*']],
            ['key' => 'pricing', 'route' => 'pricing-rules.index', 'patterns' => ['admin.pricing-rules.*']],
            ['key' => 'health', 'route' => 'health', 'patterns' => ['admin.health']],
        ];
    }

    $navigationGroups = [
        __('pro.admin.workspace') => $workspace,
        __('pro.admin.system') => $system,
    ];
@endphp

<div class="et-admin-sidebar__brand">
    <a href="{{ route('admin.dashboard') }}" aria-label="{{ __('admin.brand') }}" data-testid="admin-brand-link">
        <img src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire Admin">
    </a>
</div>

<nav class="et-admin-nav" aria-label="{{ __('admin.topbar.menu') }}">
    @foreach ($navigationGroups as $groupLabel => $items)
        @continue($items === [])
        <div class="et-admin-nav__label">{{ $groupLabel }}</div>
        @foreach ($items as $item)
            @php($active = collect($item['patterns'])->contains(fn ($pattern) => request()->routeIs($pattern)))
            <a href="{{ route('admin.'.$item['route'], $item['parameters'] ?? []) }}" data-testid="admin-nav-{{ $item['key'] }}" @if($active) aria-current="page" @endif>
                <span>{{ __('admin_nav.'.$item['key']) }}</span>
            </a>
        @endforeach
    @endforeach
</nav>

<div class="et-admin-sidebar__footer">{{ __('pro.admin.guardrail') }}</div>
