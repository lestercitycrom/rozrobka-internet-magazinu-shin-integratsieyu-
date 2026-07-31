<div class="flex h-full w-full flex-col p-4">
    <a href="{{ route('admin.dashboard') }}" class="mb-10 block text-base font-semibold tracking-tight focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.brand') }}</a>
    <nav class="space-y-1" aria-label="{{ __('admin.topbar.menu') }}">
        <a href="{{ route('admin.dashboard') }}" class="block border border-[#111111] bg-[#111111] px-3 py-2 text-sm font-medium text-white focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.nav.dashboard') }}</a>
    </nav>
</div>
