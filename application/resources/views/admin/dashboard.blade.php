<x-layouts.admin :title="__('admin.dashboard.title')">
    <div class="et-admin-pagehead">
        <div>
            <p class="et-eyebrow">{{ __('pro.admin.dashboard_kicker') }}</p>
            <h1>{{ __('pro.admin.dashboard_heading') }}</h1>
            <p>{{ __('pro.admin.dashboard_intro') }}</p>
        </div>
        <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.index') }}">{{ __('pro.admin.open_requests') }}</a>
    </div>

    <section class="et-kpis" aria-label="{{ __('pro.admin.dashboard_kicker') }}">
        <article class="et-kpi"><span class="et-kpi__label">{{ __('pro.admin.requests_attention') }}</span><div class="et-kpi__value"><strong>{{ $serviceRequestCounts['attention'] }}</strong><small>{{ __('dashboard.attention') }}</small></div></article>
        <article class="et-kpi"><span class="et-kpi__label">{{ __('pro.admin.requests_total') }}</span><div class="et-kpi__value"><strong>{{ $serviceRequestCounts['total'] }}</strong><small>{{ __('service_requests.title') }}</small></div></article>
        <article class="et-kpi"><span class="et-kpi__label">{{ __('pro.admin.orders_attention') }}</span><div class="et-kpi__value"><strong>{{ $orderCounts['attention'] }}</strong><small>{{ __('dashboard.attention') }}</small></div></article>
        <article class="et-kpi"><span class="et-kpi__label">{{ __('pro.admin.orders_total') }}</span><div class="et-kpi__value"><strong>{{ $orderCounts['total'] }}</strong><small>{{ __('orders.title') }}</small></div></article>
    </section>

    <div class="et-admin-grid">
        <section class="et-panel" aria-labelledby="admin-work-areas-heading">
            <div class="et-panel__head"><h2 id="admin-work-areas-heading">{{ __('pro.admin.work_areas') }}</h2></div>
            <div class="et-panel__body">
                <div class="et-grid-2">
                    @foreach ([
                        ['label' => __('dashboard.catalog'), 'description' => __('dashboard.catalog_description'), 'route' => 'admin.catalog.index'],
                        ['label' => __('dashboard.imports'), 'description' => __('dashboard.imports_description'), 'route' => 'admin.imports.index'],
                        ['label' => __('dashboard.services'), 'description' => __('dashboard.services_description'), 'route' => 'admin.services.index'],
                        ['label' => __('dashboard.service_requests'), 'description' => __('dashboard.service_requests_description'), 'route' => 'admin.service-requests.index'],
                    ] as $area)
                        <a class="et-card et-card--interactive" href="{{ route($area['route']) }}"><span class="et-card__body"><span class="et-card__meta">EasyTire Admin</span><strong class="et-h3" style="display:block;margin-top:.45rem">{{ $area['label'] }}</strong><span class="et-copy" style="display:block;margin-top:.5rem">{{ $area['description'] }}</span><span class="et-card__link">{{ $area['label'] }}</span></span></a>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="et-panel" aria-labelledby="admin-identity-heading">
            <div class="et-panel__head"><h2 id="admin-identity-heading">{{ __('pro.admin.logged_in') }}</h2></div>
            <div class="et-panel__body">
                <div class="et-activity-item">
                    <span class="et-activity-icon" aria-hidden="true">ET</span>
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
                </div>
                <div class="et-alert et-alert--info" style="margin-top:1.2rem"><span class="et-alert__icon" aria-hidden="true">i</span><div>{{ __('pro.admin.guardrail') }}</div></div>
                <div class="et-stack" style="margin-top:1.2rem">
                    <a class="et-btn et-btn--outline et-btn--full" href="{{ route('admin.orders.index') }}">{{ __('orders.title') }}</a>
                    <a class="et-btn et-btn--outline et-btn--full" href="{{ route('admin.content-pages.index') }}">{{ __('admin_nav.content') }}</a>
                    <a class="et-btn et-btn--outline et-btn--full" href="{{ route('admin.media.index') }}">{{ __('admin_nav.media') }}</a>
                    <a class="et-btn et-btn--outline et-btn--full" href="{{ route('admin.pricing-rules.index') }}">{{ __('admin_nav.pricing') }}</a>
                </div>
            </div>
        </aside>
    </div>
</x-layouts.admin>
