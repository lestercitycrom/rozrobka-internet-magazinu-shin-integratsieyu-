@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $presenter = new \App\Modules\Catalog\Presentation\CatalogItemPresenter($item);
    $season = $presenter->season();
    $specification = $presenter->summary();
    $cardTitle = filled($item->modelName) ? trim($item->brand.' '.$item->modelName) : $item->title;
    $requiredQuantity = $item->isIntegrationTest ? \App\Modules\NdiApi\NdiSandboxContract::QUANTITY : 1;
    $canPurchase = $sellingPrice !== null && ! $sellingPrice->isSnapshot && $item->stock->canPurchase($requiredQuantity);
    $stockState = $item->stock->state();
@endphp
<article class="et-product-card" data-testid="catalog-product" data-product-sku="{{ $item->sku }}">
    <a class="et-product-card__media" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" tabindex="-1" aria-hidden="true">
        <div class="et-product-visual">
            @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'lazy', 'class' => 'size-full object-contain'])])
        </div>
    </a>
    <div class="et-product-card__body">
        <div class="et-product-card__badges">
            @if ($item->isIntegrationTest)<span class="et-badge et-badge--red">{{ __('storefront.integration_test_product') }}</span>@elseif ($item->tire && $season)<span class="et-badge">{{ $season }}</span>@endif
            <span @class(['et-badge', 'et-badge--success' => in_array($stockState, [\App\Modules\Catalog\ReadModel\CatalogStockState::Local, \App\Modules\Catalog\ReadModel\CatalogStockState::Remote], true)])>{{ $presenter->availabilityLabel() }}</span>
        </div>
        <h2 class="et-product-card__title"><a href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" data-testid="catalog-product-link">{{ $cardTitle }}</a></h2>
        <p class="et-product-card__spec">{{ $specification }}</p>
        <div class="et-product-card__footer">
            <div class="et-product-card__price-block">
                @if ($sellingPrice)
                    <div class="et-price"><x-storefront-money :minor="$sellingPrice->money->minor" :currency="$sellingPrice->money->currency" /></div>
                    @if ($sellingPrice->isSnapshot)
                        <p class="et-caption" data-testid="snapshot-price-note">{{ __('storefront.snapshot_price_label', ['date' => \Carbon\CarbonImmutable::parse($sellingPrice->sourceObservedAt)->format('d.m.Y')]) }}</p>
                    @endif
                @else
                    <p class="et-caption">{{ __('storefront.price_unavailable') }}</p>
                @endif
            </div>
            @if ($canPurchase)
                <form method="post" action="{{ route($prefix.'cart.add') }}">
                    @csrf
                    <input type="hidden" name="sku" value="{{ $item->sku }}">
                    <input type="hidden" name="quantity" value="{{ $requiredQuantity }}">
                    <button class="et-product-card__buy" type="submit" aria-label="{{ __('storefront.add_to_cart') }}: {{ $item->title }}" data-testid="catalog-add-to-cart"><x-icon name="cart" :size="19" /><span>{{ __('storefront.add_to_cart') }}</span></button>
                </form>
            @else
                <a class="et-product-card__more et-action-link et-action-link--small" href="{{ route($prefix.'products.show', ['sku' => $item->sku]) }}" aria-label="{{ __('storefront.view_product') }}: {{ $cardTitle }}"><span class="et-action-link__label">{{ __('storefront.view_product') }}</span><x-icon name="arrow" :size="16" /></a>
            @endif
        </div>
    </div>
</article>
