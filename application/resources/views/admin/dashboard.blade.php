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
        <article class="et-kpi et-kpi--attention"><span class="et-kpi__label">{{ __('pro.admin.requests_attention') }}</span><div class="et-kpi__value"><strong>{{ $serviceRequestCounts['attention'] }}</strong><small>{{ __('dashboard.attention') }}</small></div></article>
        <article class="et-kpi et-kpi--neutral"><span class="et-kpi__label">{{ __('pro.admin.requests_total') }}</span><div class="et-kpi__value"><strong>{{ $serviceRequestCounts['total'] }}</strong><small>{{ __('service_requests.title') }}</small></div></article>
        <article class="et-kpi et-kpi--attention"><span class="et-kpi__label">{{ __('pro.admin.orders_attention') }}</span><div class="et-kpi__value"><strong>{{ $orderCounts['attention'] }}</strong><small>{{ __('dashboard.attention') }}</small></div></article>
        <article class="et-kpi et-kpi--neutral"><span class="et-kpi__label">{{ __('pro.admin.orders_total') }}</span><div class="et-kpi__value"><strong>{{ $orderCounts['total'] }}</strong><small>{{ __('orders.title') }}</small></div></article>
    </section>

    <section class="et-panel" aria-labelledby="admin-work-areas-heading">
        <div class="et-panel__head"><h2 id="admin-work-areas-heading">{{ __('pro.admin.work_areas') }}</h2></div>
        <div class="et-admin-work-list">
            @foreach ([
                ['label' => __('dashboard.catalog'), 'description' => __('dashboard.catalog_description'), 'route' => 'admin.catalog.index'],
                ['label' => __('dashboard.imports'), 'description' => __('dashboard.imports_description'), 'route' => 'admin.imports.index'],
                ['label' => __('dashboard.services'), 'description' => __('dashboard.services_description'), 'route' => 'admin.services.index'],
                ['label' => __('dashboard.service_requests'), 'description' => __('dashboard.service_requests_description'), 'route' => 'admin.service-requests.index'],
            ] as $area)
                <a class="et-admin-work-link" href="{{ route($area['route']) }}">
                    <strong>{{ $area['label'] }}</strong>
                    <small>{{ $area['description'] }}</small>
                    <span class="et-admin-work-link__arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
        <div class="et-admin-context">
            <span><strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->email }}</span>
            <span>{{ __('pro.admin.guardrail') }}</span>
        </div>
    </section>
</x-layouts.admin>
