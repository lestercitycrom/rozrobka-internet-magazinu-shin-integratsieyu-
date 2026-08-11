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
    $cardTitle = filled($item->modelName) ? trim($item->brand.' '.$item->modelName) : $item->title;
@endphp
<article class="et-product-card" data-testid="catalog-product" data-product-sku="{{ $item->sku }}">
    <a class="et-product-card__media" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" tabindex="-1" aria-hidden="true">
        <div class="et-product-visual">
            @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'lazy', 'class' => 'size-full object-contain'])])
        </div>
        <div class="et-product-card__badges">
            @if ($item->tire && $season)<span class="et-badge et-badge--red">{{ $season }}</span>@else<span></span>@endif
            <span @class(['et-badge', 'et-badge--success' => $item->stock->quantity > 0])>{{ $item->stock->quantity > 0 ? __('storefront.registered_stock') : __('storefront.no_stock') }}</span>
        </div>
    </a>
    <div class="et-product-card__body">
        <span class="et-product-card__brand">{{ $item->brand }}</span>
        <h2 class="et-product-card__title"><a href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" data-testid="catalog-product-link">{{ $cardTitle }}</a></h2>
        <p class="et-product-card__spec">{{ $specification }}</p>
        <div class="et-product-card__footer">
            <div class="et-product-card__price-block">
                @if ($sellingPrice)
                    <div class="et-price">{{ number_format($sellingPrice->money->minor / 100, 2, ',', ' ') }} <small>{{ $sellingPrice->money->currency }}</small></div>
                @else
                    <p class="et-caption"><strong>{{ __('storefront.price_unavailable') }}</strong></p>
                @endif
            </div>
            @if ($sellingPrice && $item->stock->quantity > 0)
                <form method="post" action="{{ route($prefix.'cart.add') }}">
                    @csrf
                    <input type="hidden" name="sku" value="{{ $item->sku }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="et-product-card__buy" type="submit" aria-label="{{ __('storefront.add_to_cart') }}: {{ $item->title }}" data-testid="catalog-add-to-cart"><x-icon name="cart" :size="19" /><span>{{ __('storefront.add_to_cart') }}</span></button>
                </form>
            @else
                <a class="et-product-card__more" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" aria-label="{{ __('storefront.view_product') }}: {{ $cardTitle }}"><x-icon name="arrow" :size="20" /></a>
            @endif
        </div>
    </div>
</article>
