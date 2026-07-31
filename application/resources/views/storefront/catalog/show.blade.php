@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')
@php($season = ['Summer' => __('storefront.season_summer'), 'Winter' => __('storefront.season_winter'), 'All season' => __('storefront.season_all')][$item->tire['season'] ?? ''] ?? ($item->tire['season'] ?? ''))
<x-layouts.storefront :title="'EasyTire | '.$item->title">
    <div class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        <a href="{{ route($prefix.($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire ? 'tires.index' : 'wheels.index')) }}" class="text-sm font-bold text-easytire-brand underline">{{ __('storefront.back') }}</a>
        <article class="mt-6 grid gap-8 rounded-3xl border border-easytire-border bg-white p-6 shadow-sm lg:grid-cols-[1.15fr_.85fr] lg:p-10">
            <div>
                @if($item->primaryMedia)@php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])<img data-testid="product-primary-media" src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" loading="eager" fetchpriority="high" class="mb-6 aspect-video w-full object-contain">@endif
                <p class="text-sm font-bold text-easytire-brand">{{ $item->brand }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $item->title }}</h1>
                <p class="mt-2 text-lg text-easytire-muted">{{ $item->modelName }}</p>
                <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 border-t border-easytire-border pt-6 text-sm">
                    <div><dt>{{ __('storefront.item_number') }}</dt><dd>{{ $item->sku }}</dd></div>
                    @if($item->tire)
                        <div><dt>{{ __('storefront.dimension') }}</dt><dd>{{ $item->tire['width_mm'] }}/{{ $item->tire['aspect_ratio'] }} R{{ $item->tire['rim_diameter_in'] }}</dd></div>
                        <div><dt>{{ __('storefront.season_label') }}</dt><dd>{{ $season }}</dd></div>
                    @else
                        <div><dt>{{ __('storefront.size') }}</dt><dd>{{ $item->wheel['width_in'] }} × {{ $item->wheel['diameter_in'] }}</dd></div>
                        <div><dt>{{ __('storefront.bolt_circle') }}</dt><dd>{{ $item->wheel['bolt_holes'] }}×{{ $item->wheel['pcd_mm'] }}</dd></div>
                    @endif
                </dl>
            </div>
            <aside class="h-fit rounded-2xl bg-easytire-brand-soft p-6">
                <p class="text-sm font-bold text-easytire-brand">{{ __('storefront.product_information') }}</p>
                @if($sellingPrice && $item->stock->quantity > 0)
                    <p class="mt-3 text-3xl font-black">{{ number_format($sellingPrice->money->minor / 100, 2, ',', ' ') }} {{ $sellingPrice->money->currency }}</p>
                    <p class="mt-2 text-sm text-easytire-muted">{{ __('storefront.selling_price_note') }}</p>
                    <form method="post" action="{{ route($prefix.'cart.add') }}" class="mt-5">@csrf<input type="hidden" name="sku" value="{{ $item->sku }}"><label class="block text-sm font-bold">{{ __('storefront.quantity') }}<input class="mt-1 block w-24 rounded-lg border border-easytire-border bg-white px-3 py-2" type="number" name="quantity" value="1" min="1" max="{{ min(40, $item->stock->quantity) }}"></label><button class="mt-4 min-h-11 rounded-lg bg-easytire-brand px-5 font-bold text-white hover:bg-easytire-brand-strong">{{ __('storefront.add_to_cart') }}</button></form>
                @else
                    <p class="mt-4 font-semibold">{{ $item->stock->quantity > 0 ? __('storefront.price_unavailable') : __('storefront.stock_none') }}</p>
                    <p class="mt-2 text-sm text-easytire-muted">{{ __('storefront.unavailable_note') }}</p>
                @endif
            </aside>
        </article>
    </div>
</x-layouts.storefront>
