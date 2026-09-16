@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $dimension = $presenter->dimension();
    $boltCircle = $presenter->boltCircle();
    $season = $presenter->season();
    $technicalDetails = $presenter->technicalDetails();
    $isTire = $item->productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $catalogRoute = $prefix.($isTire ? 'tires.index' : 'wheels.index');
    $catalogLabel = $isTire ? __('storefront.tires') : __('storefront.wheels');
    $productHeading = filled($item->modelName) ? trim($item->brand.' '.$item->modelName) : $item->title;
    $productDescription = trim(implode(' · ', array_filter([$productHeading, $presenter->summary(), $item->sku])));
    $requiredQuantity = $item->isIntegrationTest ? \App\Modules\NdiApi\NdiSandboxContract::QUANTITY : 1;
    $canPurchase = $sellingPrice !== null && $item->stock->canPurchase($requiredQuantity);
    $stockState = $item->stock->state();
    $productSchema = [
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $productHeading,
        'sku' => $item->sku, 'brand' => ['@type' => 'Brand', 'name' => $item->brand],
        'description' => $productDescription,
        'url' => route($prefix.'products.show', ['sku' => $item->sku]),
    ];
    if ($item->primaryMedia) $productSchema['image'] = url($item->primaryMedia['url']);
    if ($sellingPrice && in_array($stockState, [\App\Modules\Catalog\ReadModel\CatalogStockState::Local, \App\Modules\Catalog\ReadModel\CatalogStockState::Remote, \App\Modules\Catalog\ReadModel\CatalogStockState::OutOfStock], true)) {
        $productSchema['offers'] = [
            '@type' => 'Offer', 'priceCurrency' => $sellingPrice->money->currency,
            'price' => number_format($sellingPrice->money->minor / 100, 2, '.', ''),
            'availability' => $stockState === \App\Modules\Catalog\ReadModel\CatalogStockState::OutOfStock ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
            'url' => route($prefix.'products.show', ['sku' => $item->sku]),
        ];
    }
@endphp

<x-layouts.storefront :title="'EasyTire | '.$productHeading" :description="$productDescription" :image="$item->primaryMedia['url'] ?? null" :structured-data="[$productSchema]" active-section="store" :store-route="route($catalogRoute)" :store-label="$catalogLabel">
    <section class="et-section et-section--compact">
        <div class="et-container">
            <div class="et-breadcrumbs et-breadcrumbs--muted">
                <span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span>
                <span><a href="{{ route($catalogRoute) }}">{{ $catalogLabel }}</a></span>
                <span>{{ $productHeading }}</span>
            </div>

            <a class="et-btn et-btn--link" href="{{ route($catalogRoute) }}">{{ __('pro.catalog.back') }}</a>

            <article class="et-product-layout et-product-layout--spaced">
                <div>
                    <div class="et-product-gallery">
                        <div class="et-product-gallery__main">
                            <div class="et-product-visual">
                                @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'size-full object-contain'])])
                            </div>
                        </div>
                        <div class="et-product-gallery__note" data-product-media-note data-fallback-text="{{ __('storefront.image_unavailable') }}">{{ $item->primaryMedia ? __('pro.catalog.media_note') : __('storefront.image_unavailable') }}</div>
                    </div>

                    <div class="et-fitment-notice">
                        <strong>{{ __('pro.catalog.compatibility') }}</strong>
                        {{ __('fitment.notice') }}
                    </div>
                </div>

                <div class="et-product-info">
                    <div class="et-product-info__top">
                        <p class="et-eyebrow">{{ __('pro.catalog.product_kicker') }}</p>
                        @if ($item->isIntegrationTest)<span class="et-badge et-badge--red" data-testid="integration-test-product">{{ __('storefront.integration_test_product') }}</span>@endif
                        <span class="et-product-card__brand">{{ $item->brand }}</span>
                        <h1 class="et-product-title">{{ $productHeading }}</h1>
                        @if ($dimension)<p class="et-product-dimension">{{ $dimension }}</p>@endif

                        <div class="et-stock-line">
                            <span @class(['et-stock-dot' => in_array($stockState, [\App\Modules\Catalog\ReadModel\CatalogStockState::Local, \App\Modules\Catalog\ReadModel\CatalogStockState::Remote], true)]) aria-hidden="true"></span>
                            <span>{{ $presenter->availabilityDetail() }}</span>
                        </div>

                        @if ($sellingPrice)
                            <div class="et-product-price">
                                <x-storefront-money :minor="$sellingPrice->money->minor" :currency="$sellingPrice->money->currency" />
                                <small>{{ __('storefront.selling_price_note') }}</small>
                            </div>
                        @else
                            <p class="et-product-price et-product-price--unavailable" data-testid="product-price-unavailable">
                                {{ __('storefront.price_unavailable') }}
                            </p>
                        @endif
                        @if ($canPurchase)
                            <form class="et-product-purchase" method="post" action="{{ route($prefix.'cart.add') }}" data-testid="product-cart-form">
                                @csrf
                                <input type="hidden" name="sku" value="{{ $item->sku }}">
                                <div class="et-purchase-controls">
                                    <div class="et-purchase-quantity">
                                        <label class="et-label" for="product-quantity">{{ __('storefront.quantity') }}</label>
                                        <input class="et-input easytire-input" id="product-quantity" data-testid="product-quantity" type="number" name="quantity" value="{{ $requiredQuantity }}" min="{{ $requiredQuantity }}" max="{{ $item->isIntegrationTest ? $requiredQuantity : min(40, $item->stock->quantity) }}" inputmode="numeric" {{ $item->isIntegrationTest ? 'readonly' : '' }}>
                                    </div>
                                    <div class="et-purchase-actions" data-testid="product-purchase-actions">
                                        <button class="et-btn et-btn--outline" type="submit" name="intent" value="cart" data-testid="product-add-to-cart">{{ __('storefront.add_to_cart') }}</button>
                                        <button class="et-btn et-btn--primary" type="submit" name="intent" value="checkout" data-testid="product-buy-now">{{ __('storefront.buy_now') }}</button>
                                    </div>
                                </div>
                                @if ($item->isIntegrationTest)
                                    <aside class="et-sandbox-purchase-note" data-testid="integration-test-quantity-note">
                                        <span>{{ __('storefront.integration_test_product') }}</span>
                                        <small>{{ __('storefront.integration_test_quantity_note', ['quantity' => $requiredQuantity]) }}</small>
                                    </aside>
                                @endif
                            </form>
                            <p class="et-help et-purchase-fulfilment-note">{{ __('pro.catalog.buy_box_note') }}</p>
                        @else
                            <div class="et-product-unavailable" data-testid="product-unavailable">
                                <div class="et-alert et-alert--warning">
                                    <span class="et-alert__icon" aria-hidden="true">!</span>
                                    <div><strong>{{ $presenter->availabilityLabel() }}</strong><br>{{ __('storefront.unavailable_note') }}</div>
                                </div>
                                @unless ($item->isIntegrationTest)
                                    <a class="et-btn et-btn--outline et-product-unavailable__contact" href="{{ route($prefix.'content.show', ['slug' => 'kontakt']) }}" data-testid="product-availability-contact">
                                        {{ __('storefront.availability_contact') }} <x-icon name="arrow" :size="18" />
                                    </a>
                                @endunless
                            </div>
                        @endif
                    </div>

                    <h2 class="et-h3 et-product-spec-heading">{{ __('storefront.technical_details') }}</h2>
                    <dl class="et-spec-grid" data-testid="technical-details">
                        <div><dt>{{ __('storefront.item_number') }}</dt><dd>{{ $item->sku }}</dd></div>
                        <div><dt>{{ __('pro.catalog.stock') }}</dt><dd>{{ $presenter->availabilityDetail() }}</dd></div>
                        @if ($item->title !== $productHeading)<div><dt>{{ __('storefront.supplier_description') }}</dt><dd>{{ $item->title }}</dd></div>@endif
                        @if ($item->tire)
                            @if ($dimension)<div><dt>{{ __('storefront.dimension') }}</dt><dd>{{ $dimension }}</dd></div>@endif
                            @if ($season)<div><dt>{{ __('storefront.season_label') }}</dt><dd>{{ $season }}</dd></div>@endif
                        @else
                            @if ($dimension)<div><dt>{{ __('storefront.size') }}</dt><dd>{{ $dimension }}</dd></div>@endif
                            @if ($boltCircle)<div><dt>{{ __('storefront.bolt_circle') }}</dt><dd>{{ $boltCircle }}</dd></div>@endif
                        @endif
                        @foreach ($technicalDetails as $detail)
                            <div><dt>{{ $detail['label'] }}</dt><dd>{{ $detail['value'] }}</dd></div>
                        @endforeach
                    </dl>

                    @if ($item->tire)
                        @php
                            $label = collect($item->tire['label'])->filter(fn ($value) => $value !== null && $value !== '');
                            $labelNames = [
                                'rolling_resistance' => __('storefront.rolling_resistance'),
                                'wet_grip' => __('storefront.wet_grip'),
                                'noise_db' => __('storefront.external_noise'),
                                'noise_class' => __('storefront.noise_class'),
                            ];
                        @endphp
                        @if ($label->isNotEmpty())
                            <section class="et-card et-card--flat et-product-label" aria-labelledby="eu-label-heading">
                                <div class="et-card__body">
                                    <h2 class="et-h3" id="eu-label-heading">{{ __('storefront.eu_label') }}</h2>
                                    <dl class="et-grid-4 et-product-label__grid">
                                        @foreach (['rolling_resistance', 'wet_grip', 'noise_db', 'noise_class'] as $key)
                                            @if (($item->tire['label'][$key] ?? null) !== null && $item->tire['label'][$key] !== '')
                                                <div><dt class="et-caption">{{ $labelNames[$key] }}</dt><dd class="et-price et-product-label__value">{{ $item->tire['label'][$key] }}{{ $key === 'noise_db' ? ' dB' : '' }}</dd></div>
                                            @endif
                                        @endforeach
                                    </dl>
                                </div>
                            </section>
                        @endif
                    @endif
                </div>
            </article>
        </div>
    </section>
</x-layouts.storefront>
