<div class="flex h-full w-full flex-col p-4">
    <a href="{{ route('admin.dashboard') }}" class="mb-10 flex items-center gap-2 text-base font-semibold tracking-tight focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand"><img src="/brand/easytire-mark.svg" alt="" class="size-8" aria-hidden="true"><span>{{ __('admin.brand') }}</span></a>
    <nav class="space-y-1" aria-label="{{ __('admin.topbar.menu') }}">
        @foreach (['dashboard' => 'dashboard', 'catalog' => 'catalog.index', 'imports' => 'imports.index', 'services' => 'services.index'] as $item => $route)
            <a href="{{ route('admin.'.$route) }}" @class(['block border px-3 py-2 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand', 'border-easytire-brand bg-easytire-brand text-white' => request()->routeIs('admin.'.$route) || ($item === 'catalog' && request()->routeIs('admin.catalog.*')) || ($item === 'imports' && request()->routeIs('admin.imports.*')) || ($item === 'services' && request()->routeIs('admin.services.*')), 'border-transparent hover:bg-easytire-brand-soft' => ! (request()->routeIs('admin.'.$route) || ($item === 'catalog' && request()->routeIs('admin.catalog.*')) || ($item === 'imports' && request()->routeIs('admin.imports.*')) || ($item === 'services' && request()->routeIs('admin.services.*')))])>{{ __('admin.nav.'.$item) }}</a>
        @endforeach
    </nav>
</div>
