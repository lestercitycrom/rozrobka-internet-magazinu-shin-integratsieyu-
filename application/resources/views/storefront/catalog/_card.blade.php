@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $season = match ($item->tire['season'] ?? '') {
        'Summer' => __('storefront.season_summer'),
        'Winter' => __('storefront.season_winter'),
        'All season' => __('storefront.season_all'),
        default => $item->tire['season'] ?? '',
    };
    $specification = $item->tire
        ? $item->tire['width_mm'].'/'.$item->tire['aspect_ratio'].' R'.$item->tire['rim_diameter_in'].' · '.$item->tire['load_index'].$item->tire['speed_symbol'].($season ? ' · '.$season : '')
        : $item->wheel['width_in'].' × '.$item->wheel['diameter_in'].' · '.$item->wheel['bolt_holes'].'×'.$item->wheel['pcd_mm'].' · ET '.$item->wheel['offset_et_mm'];
@endphp
<article class="et-product-card easytire-card easytire-interactive" data-testid="catalog-product" data-product-sku="{{ $item->sku }}">
    <div class="et-product-card__media">
        <div class="et-product-visual">
            @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'lazy', 'class' => 'size-full object-contain'])])
        </div>
        <div class="et-product-card__badges">
            @if ($item->tire && $season)<span class="et-badge et-badge--red">{{ $season }}</span>@else<span></span>@endif
            <span @class(['et-badge', 'et-badge--success' => $item->stock->quantity > 0])>{{ $item->stock->quantity > 0 ? __('storefront.registered_stock') : __('storefront.no_stock') }}</span>
        </div>
    </div>
    <div class="et-product-card__body">
        <span class="et-product-card__brand">{{ $item->brand }}</span>
        <h2 class="et-product-card__title"><a href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" data-testid="catalog-product-link">{{ $item->title }}</a></h2>
        @if ($item->modelName)<p class="et-product-card__spec">{{ $item->modelName }}</p>@endif
        <p class="et-product-card__spec">{{ $specification }}</p>
        <p class="et-product-card__spec">{{ __('storefront.item_number') }} {{ $item->sku }}</p>
        <div class="et-product-card__footer">
            <div>
                @if ($sellingPrice)
                    <div class="et-price">{{ number_format($sellingPrice->money->minor / 100, 2, ',', ' ') }} {{ $sellingPrice->money->currency }}<small>{{ __('pro.catalog.price_backend') }}</small></div>
                @else
                    <p class="et-caption"><strong>{{ __('storefront.price_unavailable') }}</strong></p>
                @endif
                <a class="et-btn et-btn--link et-btn--small" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}">{{ __('storefront.view_product') }}</a>
            </div>
            @if ($sellingPrice && $item->stock->quantity > 0)
                <form method="post" action="{{ route($prefix.'cart.add') }}">
                    @csrf
                    <input type="hidden" name="sku" value="{{ $item->sku }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="et-icon-btn" type="submit" aria-label="{{ __('storefront.add_to_cart') }}: {{ $item->title }}" data-testid="catalog-add-to-cart">+</button>
                </form>
            @endif
        </div>
    </div>
</article>
