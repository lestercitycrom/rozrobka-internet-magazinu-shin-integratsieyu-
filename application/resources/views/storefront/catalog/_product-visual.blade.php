@if ($item->primaryMedia)
    @php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])
    <img data-testid="product-primary-media" src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" {{ $attributes }}>
@else
    <div data-testid="product-visual-fallback" class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-b from-white to-easytire-surface px-4 text-center" role="img" aria-label="{{ __('storefront.image_unavailable_for', ['product' => $item->title]) }}">
        @if ($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire)
            <svg viewBox="0 0 240 180" class="h-auto w-2/3 max-w-48 text-slate-400" aria-hidden="true" focusable="false">
                <ellipse cx="120" cy="90" rx="61" ry="72" fill="none" stroke="currentColor" stroke-width="14"/>
                <ellipse cx="120" cy="90" rx="36" ry="48" fill="none" stroke="currentColor" stroke-width="4" opacity=".65"/>
                <path d="M74 53h20m-24 37h20m-16 37h20m72-74h-20m24 37h-20m16 37h-20" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="3" opacity=".5"/>
            </svg>
        @else
            <svg viewBox="0 0 240 180" class="h-auto w-2/3 max-w-48 text-slate-400" aria-hidden="true" focusable="false">
                <circle cx="120" cy="90" r="65" fill="none" stroke="currentColor" stroke-width="8"/>
                <circle cx="120" cy="90" r="51" fill="none" stroke="currentColor" stroke-width="3" opacity=".65"/>
                <circle cx="120" cy="90" r="24" fill="none" stroke="currentColor" stroke-width="4" opacity=".65"/>
            </svg>
        @endif
        <span class="max-w-full rounded-full border border-easytire-border bg-white/90 px-3 py-1 text-[.68rem] font-bold uppercase tracking-[.12em] text-easytire-muted">{{ $item->brand }} · {{ __('storefront.image_unavailable') }}</span>
    </div>
@endif
