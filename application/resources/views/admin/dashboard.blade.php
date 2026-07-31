<x-layouts.admin :title="__('admin.dashboard.title')">
    <div class="max-w-3xl">
        <p class="text-sm font-medium text-[#666660]">{{ __('admin.brand') }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('admin.dashboard.heading') }}</h1>
        <p class="mt-3 max-w-2xl leading-7 text-[#666660]">{{ __('admin.dashboard.intro') }}</p>

        <section class="mt-10 border border-[#D8D8D2] bg-white p-5" aria-labelledby="identity-heading">
            <h2 id="identity-heading" class="text-sm font-semibold">{{ __('admin.dashboard.identity') }}</h2>
            <p class="mt-2 text-sm text-[#666660]">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        </section>
    </div>
</x-layouts.admin>
