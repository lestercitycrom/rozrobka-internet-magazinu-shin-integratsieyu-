<div class="flex h-full w-full flex-col p-4">
    <a href="{{ route('admin.dashboard') }}" class="mb-9 flex items-center gap-3 rounded-xl px-2 py-2 text-base font-black tracking-tight focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand"><img src="/brand/easytire-mark.svg" alt="" class="size-9" aria-hidden="true"><span>{{ __('admin.brand') }}</span></a>
    <p class="mb-3 px-3 text-xs font-bold uppercase tracking-[0.16em] text-easytire-muted">{{ __('admin.topbar.menu') }}</p>
    <nav class="space-y-1" aria-label="{{ __('admin.topbar.menu') }}">
        @foreach (['dashboard' => 'dashboard', 'catalog' => 'catalog.index', 'imports' => 'imports.index', 'services' => 'services.index', 'content' => 'content-pages.index', 'pricing' => 'pricing-rules.index', 'orders' => 'orders.index', 'media' => 'media.index', 'health' => 'health'] as $item => $route)
            @php($active = request()->routeIs('admin.'.$route) || ($item === 'catalog' && request()->routeIs('admin.catalog.*')) || ($item === 'imports' && request()->routeIs('admin.imports.*')) || ($item === 'services' && request()->routeIs('admin.services.*')) || ($item === 'content' && request()->routeIs('admin.content-pages.*')) || ($item === 'pricing' && request()->routeIs('admin.pricing-rules.*')) || ($item === 'orders' && request()->routeIs('admin.orders.*')) || ($item === 'media' && request()->routeIs('admin.media.*')))
            <a href="{{ route('admin.'.$route) }}" @class(['block min-h-11 rounded-lg px-3 py-2.5 text-sm font-bold transition focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand', 'bg-easytire-brand text-white shadow-sm' => $active, 'text-easytire-muted hover:bg-easytire-brand-soft hover:text-easytire-ink' => ! $active])>{{ $item === 'content' ? 'Innholdssider' : ($item === 'pricing' ? 'Prisregler' : ($item === 'orders' ? __('orders.title') : ($item === 'health' ? __('health.title') : __('admin.nav.'.$item)))) }}</a>
        @endforeach
    </nav>
</div>
