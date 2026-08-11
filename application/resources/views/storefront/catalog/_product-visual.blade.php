@if ($item->primaryMedia)
    @php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])
    <img data-testid="product-primary-media" src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" {{ $attributes }}>
@else
    @php($fallbackClass = trim('et-product-placeholder '.($attributes?->get('class') ?? '')))
    <div data-testid="product-visual-fallback" class="{{ $fallbackClass }}" role="img" aria-label="{{ __('storefront.image_unavailable_for', ['product' => $item->title]) }}">
        @if ($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire)
            <div class="et-tire-illustration" aria-hidden="true"></div>
        @else
            <div class="et-wheel-illustration" aria-hidden="true"></div>
        @endif
        <span class="et-placeholder-label">{{ $item->brand }} · {{ __('storefront.image_unavailable') }}</span>
    </div>
@endif
