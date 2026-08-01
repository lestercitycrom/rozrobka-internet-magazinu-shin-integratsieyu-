@if ($item->primaryMedia)
    @php($mediaAlt = app()->getLocale() === 'ru' ? ($item->primaryMedia['alt_ru'] ?: $item->primaryMedia['alt_nb']) : $item->primaryMedia['alt_nb'])
    <img data-testid="product-primary-media" src="{{ $item->primaryMedia['url'] }}" alt="{{ $mediaAlt }}" {{ $attributes }}>
@else
    <div data-testid="product-visual-fallback" class="grid size-full place-items-center bg-easytire-surface" aria-hidden="true">
        @if ($item->productType === \App\Modules\Catalog\Enums\ProductType::Tire)
            <svg viewBox="0 0 240 180" class="h-auto w-3/4 max-w-56 text-easytire-ink" focusable="false"><ellipse cx="120" cy="90" rx="67" ry="78" fill="none" stroke="currentColor" stroke-width="18"/><ellipse cx="120" cy="90" rx="33" ry="43" fill="none" stroke="currentColor" stroke-width="5"/><path d="M74 42 166 138M69 63l102 54M69 117l102-54M74 138l92-96" fill="none" stroke="currentColor" stroke-width="4" opacity=".42"/></svg>
        @else
            <svg viewBox="0 0 240 180" class="h-auto w-3/4 max-w-56 text-easytire-ink" focusable="false"><circle cx="120" cy="90" r="64" fill="none" stroke="currentColor" stroke-width="7"/><circle cx="120" cy="90" r="24" fill="none" stroke="currentColor" stroke-width="7"/><path d="m120 38 15 38 41 2-32 26 10 40-34-22-34 22 10-40-32-26 41-2z" fill="currentColor" opacity=".13"/><circle cx="120" cy="90" r="6" fill="currentColor"/></svg>
        @endif
    </div>
@endif
