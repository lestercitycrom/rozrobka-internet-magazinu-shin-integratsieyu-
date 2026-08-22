@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $presenter = new \App\Modules\Catalog\Presentation\CatalogItemPresenter($item);
    $season = $presenter->season();
    $specification = $presenter->summary();
    $cardTitle = filled($item->modelName) ? trim($item->brand.' '.$item->modelName) : $item->title;
@endphp
<article class="et-product-card" data-testid="catalog-product" data-product-sku="{{ $item->sku }}">
    <a class="et-product-card__media" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" tabindex="-1" aria-hidden="true">
        <div class="et-product-visual">
            @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'lazy', 'class' => 'size-full object-contain'])])
        </div>
        <div class="et-product-card__badges">
            @if ($item->isIntegrationTest)<span class="et-badge et-badge--red">{{ __('storefront.integration_test_product') }}</span>@elseif ($item->tire && $season)<span class="et-badge et-badge--red">{{ $season }}</span>@else<span></span>@endif
            <span @class(['et-badge', 'et-badge--success' => $item->stock->isFresh()])>{{ ! $item->stock->isFresh() ? __('storefront.stock_stale') : ($item->stock->isAvailableLocally() ? __('storefront.stock_tromso') : __('storefront.stock_remote')) }}</span>
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
            @if ($sellingPrice && $item->stock->quantity > 0 && $item->stock->isFresh())
                <form method="post" action="{{ route($prefix.'cart.add') }}">
                    @csrf
                    <input type="hidden" name="sku" value="{{ $item->sku }}">
                    <input type="hidden" name="quantity" value="{{ $item->isIntegrationTest ? (int) config('ndi_api.sandbox.quantity', 2) : 1 }}">
                    <button class="et-product-card__buy" type="submit" aria-label="{{ __('storefront.add_to_cart') }}: {{ $item->title }}" data-testid="catalog-add-to-cart"><x-icon name="cart" :size="19" /><span>{{ __('storefront.add_to_cart') }}</span></button>
                </form>
            @else
                <a class="et-product-card__more" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" aria-label="{{ __('storefront.view_product') }}: {{ $cardTitle }}"><x-icon name="arrow" :size="20" /></a>
            @endif
        </div>
    </div>
</article>
