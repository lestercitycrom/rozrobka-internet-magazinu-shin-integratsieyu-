<x-layouts.admin :title="__('public_service_prices.title')">
    <div class="min-w-0 space-y-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('public_service_prices.heading') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('public_service_prices.intro') }}</p>
            </div>
            <a class="inline-flex min-h-11 items-center rounded-lg bg-easytire-brand px-4 text-sm font-bold text-white" href="{{ route('admin.public-service-prices.create') }}">{{ __('public_service_prices.new') }}</a>
        </header>

        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif

        <div class="easytire-card w-full max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl md:max-w-full">
            <div class="w-full min-w-0 overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('public_service_prices.table_label') }}">
                <table class="w-full min-w-[58rem] text-left text-sm">
                    <thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('public_service_prices.order') }}</th><th class="p-4">{{ __('public_service_prices.category') }}</th><th class="p-4">{{ __('public_service_prices.item') }}</th><th class="p-4">{{ __('public_service_prices.price') }}</th><th class="p-4">{{ __('public_service_prices.status') }}</th><th class="p-4"></th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @forelse ($prices as $price)
                            <tr class="hover:bg-easytire-brand-soft/50" data-testid="public-service-price-row">
                                <td class="p-4">{{ $price->sort_order }}</td>
                                <td class="p-4"><strong>{{ $price->category_title_nb }}</strong><small class="mt-1 block text-easytire-muted">{{ $price->category_code }}</small></td>
                                <td class="p-4"><strong>{{ $price->title_nb }}</strong><small class="mt-1 block text-easytire-muted">{{ $price->code }}</small></td>
                                <td class="p-4 font-bold">{{ number_format($price->price_minor / 100, 2, ',', ' ') }} NOK</td>
                                <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $price->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $price->is_active ? __('public_service_prices.active') : __('public_service_prices.inactive') }}</span></td>
                                <td class="p-4 text-right"><a class="font-semibold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.public-service-prices.edit', $price) }}">{{ __('public_service_prices.edit') }}</a></td>
                            </tr>
                        @empty
                            <tr><td class="p-6 text-easytire-muted" colspan="6">{{ __('public_service_prices.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $prices->links() }}
    </div>
</x-layouts.admin>
