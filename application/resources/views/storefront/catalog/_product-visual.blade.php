@php
    $isTire = $item->productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $fallbackClass = trim('et-product-placeholder et-product-placeholder--'.($isTire ? 'tire' : 'wheel').' '.($attributes?->get('class') ?? ''));
    $fallbackContext = trim(implode(' ', array_filter([$item->brand, $item->modelName])));
@endphp

@if ($item->primaryMedia)
    @php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])
    <img data-testid="product-primary-media" data-product-media-image src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" {{ $attributes }}>
@endif

<div
    data-testid="product-visual-fallback"
    data-product-fallback-kind="{{ $isTire ? 'tire' : 'wheel' }}"
    @if ($item->primaryMedia) data-product-media-fallback hidden @endif
    class="{{ $fallbackClass }}"
    role="img"
    aria-label="{{ __('storefront.image_unavailable_for', ['product' => $item->title]) }}"
>
    <x-icon :name="$isTire ? 'tire' : 'wheel'" :size="96" class="et-fallback-blueprint" data-testid="product-fallback-blueprint" />

    <span class="et-fallback-meta" aria-hidden="true">
        <span class="et-fallback-meta__type">{{ $isTire ? __('storefront.fallback_tire_type') : __('storefront.fallback_wheel_type') }}</span>
        <span class="et-fallback-meta__status">{{ __('storefront.image_unavailable_short') }}</span>
        @if ($fallbackContext !== '')
            <span class="et-fallback-meta__context">{{ $fallbackContext }}</span>
        @endif
    </span>
</div>
