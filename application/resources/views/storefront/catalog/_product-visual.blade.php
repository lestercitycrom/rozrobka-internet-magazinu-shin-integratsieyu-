@if ($item->primaryMedia)
    @php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])
    <img data-testid="product-primary-media" src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" {{ $attributes }}>
@else
    <div data-testid="product-visual-fallback" class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-b from-white to-easytire-surface px-4 text-center" role="img" aria-label="{{ __('storefront.image_unavailable_for', ['product' => $item->title]) }}">
        @if ($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire)
            <svg viewBox="0 0 240 180" class="h-auto w-2/3 max-w-48 text-easytire-ink" aria-hidden="true" focusable="false"><ellipse cx="120" cy="90" rx="67" ry="78" fill="none" stroke="currentColor" stroke-width="18"/><ellipse cx="120" cy="90" rx="33" ry="43" fill="none" stroke="currentColor" stroke-width="5"/><path d="M74 42 166 138M69 63l102 54M69 117l102-54M74 138l92-96" fill="none" stroke="currentColor" stroke-width="4" opacity=".42"/></svg>
        @else
            <svg viewBox="0 0 240 180" class="h-auto w-2/3 max-w-48 text-easytire-ink" aria-hidden="true" focusable="false"><circle cx="120" cy="90" r="64" fill="none" stroke="currentColor" stroke-width="7"/><circle cx="120" cy="90" r="24" fill="none" stroke="currentColor" stroke-width="7"/><path d="m120 38 15 38 41 2-32 26 10 40-34-22-34 22 10-40-32-26 41-2z" fill="currentColor" opacity=".13"/><circle cx="120" cy="90" r="6" fill="currentColor"/></svg>
        @endif
        <span class="max-w-full rounded-full border border-easytire-border bg-white/90 px-3 py-1 text-[.68rem] font-bold uppercase tracking-[.12em] text-easytire-muted">{{ $item->brand }} · {{ __('storefront.image_unavailable') }}</span>
    </div>
@endif
