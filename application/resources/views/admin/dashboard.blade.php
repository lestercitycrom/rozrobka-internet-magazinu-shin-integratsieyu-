<x-layouts.admin :title="__('admin.dashboard.title')">
    <div class="max-w-3xl">
        <p class="text-sm font-medium text-easytire-muted">{{ __('admin.brand') }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('admin.dashboard.heading') }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.dashboard.intro') }}</p>
        <section class="mt-6 border border-easytire-border bg-easytire-panel p-5"><h2 class="font-semibold">{{ __('orders.title') }}</h2><p>{{ $orderCounts['total'] }} total · {{ $orderCounts['attention'] }} attention</p></section>

        <section class="mt-10 rounded-2xl border border-easytire-border bg-easytire-panel p-5" aria-labelledby="identity-heading">
            <h2 id="identity-heading" class="text-sm font-semibold">{{ __('admin.dashboard.identity') }}</h2>
            <p class="mt-2 text-sm text-easytire-muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        </section>

        <section class="mt-6" aria-labelledby="admin-actions-heading">
            <h2 id="admin-actions-heading" class="text-sm font-semibold">{{ __('dashboard.actions') }}</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach (['catalog' => 'catalog.index', 'imports' => 'imports.index', 'services' => 'services.index'] as $action => $route)
                    <a href="{{ route('admin.'.$route) }}" class="rounded-lg border border-easytire-border bg-easytire-panel p-4 transition-colors hover:border-easytire-brand hover:bg-easytire-brand-soft focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">
                        <span class="block text-sm font-semibold">{{ __('dashboard.'.$action) }}</span>
                        <span class="mt-1 block text-sm text-easytire-muted">{{ __('dashboard.'.$action.'_description') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.admin>
