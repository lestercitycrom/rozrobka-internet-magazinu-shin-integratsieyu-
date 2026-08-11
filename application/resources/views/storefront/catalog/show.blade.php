@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $dimension = $presenter->dimension();
    $boltCircle = $presenter->boltCircle();
    $season = $presenter->season();
    $technicalDetails = $presenter->technicalDetails();
    $isTire = $item->productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $catalogRoute = $prefix.($isTire ? 'tires.index' : 'wheels.index');
    $catalogLabel = $isTire ? __('storefront.tires') : __('storefront.wheels');
@endphp

<x-layouts.storefront :title="'EasyTire | '.$item->title" active-section="store" :store-route="route($catalogRoute)" :store-label="$catalogLabel">
    <section class="et-section et-section--compact">
        <div class="et-container">
            <div class="et-breadcrumbs" style="color:var(--et-muted)">
                <span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span>
                <span><a href="{{ route($catalogRoute) }}">{{ $catalogLabel }}</a></span>
                <span>{{ $item->title }}</span>
            </div>

            <a class="et-btn et-btn--link" href="{{ route($catalogRoute) }}">{{ __('pro.catalog.back') }}</a>

            <article class="et-product-layout" style="margin-top:1rem">
                <div>
                    <div class="et-product-gallery">
                        <div class="et-product-gallery__main">
                            <div class="et-product-visual">
                                @include('storefront.catalog._product-visual', ['item' => $item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'size-full object-contain'])])
                            </div>
                        </div>
                        <div class="et-product-gallery__note">{{ $item->primaryMedia ? __('pro.catalog.media_note') : __('storefront.image_unavailable') }}</div>
                    </div>

                    <div class="et-fitment-notice">
                        <strong>{{ __('pro.catalog.compatibility') }}</strong>
                        {{ __('fitment.notice') }}
                    </div>
                </div>

                <div class="et-product-info">
                    <div class="et-product-info__top">
                        <p class="et-eyebrow">{{ __('pro.catalog.product_kicker') }}</p>
                        <span class="et-product-card__brand">{{ $item->brand }}</span>
                        <h1 class="et-product-title">{{ $item->title }}</h1>
                        @if ($item->modelName)<p class="et-product-subtitle">{{ $item->modelName }}</p>@endif

                        <div class="et-stock-line">
                            <span @class(['et-stock-dot' => $item->stock->quantity > 0]) aria-hidden="true"></span>
                            <span>{{ $item->stock->quantity > 0 ? __('storefront.registered_stock') : __('storefront.no_stock') }}</span>
                        </div>

                        @if ($sellingPrice && $item->stock->quantity > 0)
                            <div class="et-product-price">
                                {{ number_format($sellingPrice->money->minor / 100, 2, ',', ' ') }} {{ $sellingPrice->money->currency }}
                                <small>{{ __('storefront.selling_price_note') }}</small>
                            </div>
                            <form method="post" action="{{ route($prefix.'cart.add') }}" data-testid="product-cart-form">
                                @csrf
                                <input type="hidden" name="sku" value="{{ $item->sku }}">
                                <div class="et-quantity-row">
                                    <div>
                                        <label class="et-label" for="product-quantity">{{ __('storefront.quantity') }}</label>
                                        <input class="et-input easytire-input" id="product-quantity" data-testid="product-quantity" type="number" name="quantity" value="1" min="1" max="{{ min(40, $item->stock->quantity) }}" inputmode="numeric">
                                    </div>
                                    <button class="et-btn et-btn--primary" type="submit" style="align-self:end" data-testid="product-add-to-cart">{{ __('storefront.add_to_cart') }}</button>
                                </div>
                            </form>
                            <p class="et-help">{{ __('pro.catalog.buy_box_note') }}</p>
                        @else
                            <div class="et-alert et-alert--warning" style="margin-top:1.2rem">
                                <span class="et-alert__icon" aria-hidden="true">!</span>
                                <div><strong>{{ $item->stock->quantity > 0 ? __('storefront.price_unavailable') : __('storefront.stock_none') }}</strong><br>{{ __('storefront.unavailable_note') }}</div>
                            </div>
                        @endif
                    </div>

                    <h2 class="et-h3" style="margin:1.5rem 0 .8rem">{{ __('storefront.technical_details') }}</h2>
                    <dl class="et-spec-grid" data-testid="technical-details">
                        <div><dt>{{ __('storefront.item_number') }}</dt><dd>{{ $item->sku }}</dd></div>
                        <div><dt>{{ __('pro.catalog.stock') }}</dt><dd>{{ $item->stock->quantity }}</dd></div>
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
                            <section class="et-card et-card--flat" style="margin-top:1rem" aria-labelledby="eu-label-heading">
                                <div class="et-card__body">
                                    <h2 class="et-h3" id="eu-label-heading">{{ __('storefront.eu_label') }}</h2>
                                    <dl class="et-grid-4" style="margin-top:1rem">
                                        @foreach (['rolling_resistance', 'wet_grip', 'noise_db', 'noise_class'] as $key)
                                            @if (($item->tire['label'][$key] ?? null) !== null && $item->tire['label'][$key] !== '')
                                                <div><dt class="et-caption">{{ $labelNames[$key] }}</dt><dd class="et-price" style="margin:0">{{ $item->tire['label'][$key] }}{{ $key === 'noise_db' ? ' dB' : '' }}</dd></div>
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
