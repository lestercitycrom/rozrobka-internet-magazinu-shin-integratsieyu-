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
    <svg class="et-fallback-blueprint" data-testid="product-fallback-blueprint" viewBox="0 0 360 240" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
        @if ($isTire)
            <g class="et-fallback-blueprint__construction" fill="none" vector-effect="non-scaling-stroke">
                <path d="M214 -30C146 32 121 105 134 181c8 45 31 82 66 111" />
                <path d="M247 -30c-65 65-86 137-72 209 9 46 31 83 65 113" />
                <path d="M285 -30c-57 68-74 139-58 208 11 47 34 84 69 114" />
                <path class="et-fallback-blueprint__detail" d="m152 45 45 18m-61 37 48 13m-52 43 50 5m-39 47 47-8" />
                <path class="et-fallback-blueprint__measure" d="M109 22v180m-7-172h14m-14 164h14M94 30h15m-15 164h15" />
                <path class="et-fallback-blueprint__measure" d="M315 34h31m-31 172h31M332 34v172" />
            </g>
            <g class="et-fallback-blueprint__ticks" aria-hidden="true">
                <path d="M319 58h10m-10 25h6m-6 25h10m-10 25h6m-6 25h10m-10 25h6" />
            </g>
        @else
            <g class="et-fallback-blueprint__construction" fill="none" vector-effect="non-scaling-stroke">
                <circle cx="260" cy="120" r="130" />
                <circle cx="260" cy="120" r="91" />
                <circle class="et-fallback-blueprint__detail" cx="260" cy="120" r="31" />
                <path class="et-fallback-blueprint__detail" d="M244 91 219 38m12 70-58-17m62 38-50 32m70-12-10 61m30-63 38 49m-17-67 63 5m-67-25 48-43m-73 34 8-62" />
                <circle class="et-fallback-blueprint__measure" cx="260" cy="120" r="61" stroke-dasharray="2 9" />
                <path class="et-fallback-blueprint__measure" d="M111 45h198M111 38v14m198-14v14M126 214h187m-187-7v14m187-14v14" />
            </g>
            <g class="et-fallback-blueprint__ticks" aria-hidden="true">
                <path d="M122 67h30m-30 18h18m-18 18h30m-30 18h18m-18 18h30m-30 18h18" />
            </g>
        @endif
    </svg>

    <span class="et-fallback-meta" aria-hidden="true">
        <span class="et-fallback-meta__type">{{ $isTire ? __('storefront.fallback_tire_type') : __('storefront.fallback_wheel_type') }}</span>
        <span class="et-fallback-meta__status">{{ __('storefront.image_unavailable_short') }}</span>
        @if ($fallbackContext !== '')
            <span class="et-fallback-meta__context">{{ $fallbackContext }}</span>
        @endif
    </span>
</div>
