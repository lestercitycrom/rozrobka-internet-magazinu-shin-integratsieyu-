@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $dimension = $presenter->dimension();
    $boltCircle = $presenter->boltCircle();
    $season = $presenter->season();
    $technicalDetails = $presenter->technicalDetails();
@endphp
<x-layouts.storefront :title="'EasyTire | '.$item->title" :active-section="$presenter->activeSection()">
    <div class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
        <a href="{{ route($prefix.($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire ? 'tires.index' : 'wheels.index')) }}" class="inline-flex min-h-11 items-center rounded text-sm font-bold text-easytire-brand underline decoration-2 underline-offset-4 hover:text-easytire-brand-strong">{{ __('storefront.back') }}</a>
        <article class="easytire-card mt-4 grid gap-8 rounded-3xl p-5 sm:p-7 lg:mt-6 lg:grid-cols-[1.15fr_.85fr] lg:p-10">
            <div>
                <div class="mb-7 aspect-[4/3] overflow-hidden rounded-2xl bg-easytire-surface">@include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'size-full object-contain p-6 sm:p-10'])])</div>
                <p class="text-sm font-bold text-easytire-brand">{{ $item->brand }}</p><h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $item->title }}</h1><p class="mt-2 text-lg text-easytire-muted">{{ $item->modelName }}</p>
                <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 border-t border-easytire-border pt-6 text-sm">
                    <div><dt class="text-easytire-muted">{{ __('storefront.item_number') }}</dt><dd class="mt-1 font-bold">{{ $item->sku }}</dd></div>
                    @if ($item->tire)
                        @if ($dimension)<div><dt class="text-easytire-muted">{{ __('storefront.dimension') }}</dt><dd class="mt-1 font-bold">{{ $dimension }}</dd></div>@endif
                        @if ($season)<div><dt class="text-easytire-muted">{{ __('storefront.season_label') }}</dt><dd class="mt-1 font-bold">{{ $season }}</dd></div>@endif
                    @else
                        @if ($dimension)<div><dt class="text-easytire-muted">{{ __('storefront.size') }}</dt><dd class="mt-1 font-bold">{{ $dimension }}</dd></div>@endif
                        @if ($boltCircle)<div><dt class="text-easytire-muted">{{ __('storefront.bolt_circle') }}</dt><dd class="mt-1 font-bold">{{ $boltCircle }}</dd></div>@endif
                    @endif
                </dl>

                @if ($technicalDetails !== [])
                    <section class="mt-8 rounded-2xl border border-easytire-border bg-easytire-surface p-5" aria-labelledby="technical-details-heading">
                        <h2 id="technical-details-heading" class="text-lg font-black">{{ __('storefront.technical_details') }}</h2>
                        <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            @foreach ($technicalDetails as $detail)
                                <div><dt class="text-easytire-muted">{{ $detail['label'] }}</dt><dd class="mt-1 font-bold">{{ $detail['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($item->tire)
                    @php($label = collect($item->tire['label'])->filter(fn ($value) => $value !== null && $value !== ''))
                    @php($labelNames = ['rolling_resistance' => __('storefront.rolling_resistance'), 'wet_grip' => __('storefront.wet_grip'), 'noise_db' => __('storefront.external_noise'), 'noise_class' => __('storefront.noise_class')])
                    @if ($label->isNotEmpty())
                        <section class="mt-6 rounded-2xl border border-easytire-border bg-white p-5" aria-labelledby="eu-label-heading">
                            <h2 id="eu-label-heading" class="text-lg font-black">{{ __('storefront.eu_label') }}</h2>
                            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach (['rolling_resistance', 'wet_grip', 'noise_db', 'noise_class'] as $key)
                                    @if (($item->tire['label'][$key] ?? null) !== null && $item->tire['label'][$key] !== '')
                                        <div class="rounded-xl bg-easytire-surface p-3"><dt class="text-xs font-semibold text-easytire-muted">{{ $labelNames[$key] }}</dt><dd class="mt-2 text-xl font-black">{{ $item->tire['label'][$key] }}{{ $key === 'noise_db' ? ' dB' : '' }}</dd></div>
                                    @endif
                                @endforeach
                            </dl>
                        </section>
                    @endif
                @endif
            </div>
            <aside class="h-fit rounded-2xl border border-red-100 bg-easytire-brand-soft p-6 sm:p-7"><p class="text-sm font-bold text-easytire-brand">{{ __('storefront.product_information') }}</p>@if($sellingPrice && $item->stock->quantity > 0)<p class="mt-3 text-3xl font-black">{{ number_format($sellingPrice->money->minor / 100, 2, ',', ' ') }} {{ $sellingPrice->money->currency }}</p><p class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('storefront.selling_price_note') }}</p><form method="post" action="{{ route($prefix.'cart.add') }}" class="mt-6">@csrf<input type="hidden" name="sku" value="{{ $item->sku }}"><label for="product-quantity" class="block text-sm font-bold">{{ __('storefront.quantity') }}</label><input id="product-quantity" class="easytire-input mt-2 block min-h-11 w-28 rounded-lg px-3" type="number" name="quantity" value="1" min="1" max="{{ min(40, $item->stock->quantity) }}" inputmode="numeric"><button class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-easytire-brand px-5 font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong hover:shadow-md focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">{{ __('storefront.add_to_cart') }}</button></form>@else<p class="mt-4 font-semibold">{{ $item->stock->quantity > 0 ? __('storefront.price_unavailable') : __('storefront.stock_none') }}</p><p class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('storefront.unavailable_note') }}</p>@endif</aside>
        </article>
    </div>
</x-layouts.storefront>
