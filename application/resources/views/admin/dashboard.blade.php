<x-layouts.admin :title="__('admin.dashboard.title')">
    <div class="max-w-5xl">
        <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('admin.brand') }}</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.dashboard.heading') }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.dashboard.intro') }}</p>

        <section class="easytire-card mt-8 grid gap-4 rounded-2xl p-6 sm:grid-cols-2" aria-labelledby="orders-overview-heading">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.12em] text-easytire-muted">{{ __('orders.title') }}</p>
                <h2 id="orders-overview-heading" class="mt-2 text-3xl font-black">{{ $orderCounts['total'] }}</h2>
            </div>
            <div class="border-t border-easytire-border pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
                <p class="text-sm font-bold text-easytire-muted">attention</p>
                <p class="mt-2 text-3xl font-black text-easytire-brand">{{ $orderCounts['attention'] }}</p>
            </div>
        </section>

        <section class="easytire-card mt-8 rounded-2xl p-6" aria-labelledby="identity-heading">
            <h2 id="identity-heading" class="text-sm font-semibold">{{ __('admin.dashboard.identity') }}</h2>
            <p class="mt-2 text-sm text-easytire-muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        </section>

        <section class="mt-8" aria-labelledby="admin-actions-heading">
            <h2 id="admin-actions-heading" class="text-sm font-semibold">{{ __('dashboard.actions') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach (['catalog' => 'catalog.index', 'imports' => 'imports.index', 'services' => 'services.index'] as $action => $route)
                    <a href="{{ route('admin.'.$route) }}" class="easytire-card easytire-interactive rounded-2xl p-5 focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">
                        <span class="block text-sm font-semibold">{{ __('dashboard.'.$action) }}</span>
                        <span class="mt-1 block text-sm text-easytire-muted">{{ __('dashboard.'.$action.'_description') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.admin>
