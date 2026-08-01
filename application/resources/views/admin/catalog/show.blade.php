<x-layouts.admin :title="__('admin.catalog.details')">
    <div class="mx-auto max-w-5xl space-y-6">
        <a class="text-sm font-semibold text-easytire-brand underline" href="{{ route('admin.catalog.index') }}">← {{ __('admin.catalog.back') }}</a>

        <header class="rounded-2xl border border-easytire-border bg-white p-6 shadow-sm">
            <p class="text-sm font-bold uppercase tracking-wide text-easytire-brand">{{ $product->product_type->value }}</p>
            <h1 class="mt-2 text-3xl font-black text-easytire-ink">{{ $product->title }}</h1>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="font-semibold text-easytire-muted">{{ __('admin.catalog.brand') }}</dt><dd class="mt-1 font-bold">{{ $product->brand?->canonical_name }}</dd></div><div><dt class="font-semibold text-easytire-muted">{{ __('admin.catalog.type') }}</dt><dd class="mt-1 font-bold">{{ $product->product_type->value }}</dd></div><div><dt class="font-semibold text-easytire-muted">{{ __('admin.catalog.status') }}</dt><dd class="mt-1 font-bold">{{ __('catalog.statuses.'.$product->publication_status->value) }}</dd></div></dl>
        </header>

        @if(session('status'))<p class="border-l-4 border-easytire-brand bg-easytire-brand-soft p-3">{{ session('status') }}</p>@endif
        <section class="rounded-2xl border border-easytire-border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">{{ __('catalog.publication') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('catalog.publication_help') }}</p><form method="POST" action="{{ route('admin.catalog.publication.update', $product) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf @method('PUT')<label class="text-sm font-semibold">{{ __('admin.catalog.status') }}<select name="publication_status" class="mt-1 block rounded-lg border border-easytire-border bg-white px-3 py-2">@foreach($publicationStatuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $product->publication_status->value) === $status->value)>{{ __('catalog.statuses.'.$status->value) }}</option>@endforeach</select></label><button class="rounded-lg bg-easytire-brand px-4 py-2 text-sm font-bold text-white">{{ __('catalog.update_publication') }}</button></form>@error('publication_status')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</section>

        <section class="rounded-2xl border border-easytire-border bg-white p-6 shadow-sm"><div><h2 class="text-lg font-black">{{ __('catalog.supplier_data') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('catalog.supplier_data_help') }}</p></div><div class="mt-5 space-y-4">@forelse ($product->supplierSkus as $sku) @php($stockTotal = $sku->currentStock->filter(fn ($stock) => $stock->warehouse?->is_active)->sum('quantity')) <article class="rounded-xl border border-easytire-border p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-black">{{ $sku->external_sku }}</h3><p class="mt-1 text-sm text-easytire-muted">{{ __('catalog.supplier') }}: {{ $sku->supplier?->name }} · {{ __('catalog.canonical_state') }}: {{ $sku->catalog_state->value }}</p></div><div class="rounded-lg bg-easytire-brand-soft px-3 py-2 text-sm font-bold">{{ __('catalog.current_stock') }}: {{ $stockTotal }}</div></div><div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-easytire-border text-easytire-muted"><tr><th class="pb-2 pr-4">{{ __('catalog.price_kind') }}</th><th class="pb-2 pr-4">{{ __('catalog.source_list') }}</th><th class="pb-2 pr-4">{{ __('catalog.amount') }}</th><th class="pb-2">{{ __('catalog.observed') }}</th></tr></thead><tbody>@forelse($sku->prices->sortBy(fn ($price) => $price->priceList?->tax_mode?->value) as $price) @if($price->priceList)<tr class="border-b border-easytire-border/70"><td class="py-2 pr-4 font-semibold">{{ __('catalog.tax_modes.'.$price->priceList->tax_mode->value) }}</td><td class="py-2 pr-4">{{ $price->priceList->code }} · {{ $price->priceList->currency }}</td><td class="py-2 pr-4">{{ $price->amount }}</td><td class="py-2">{{ $price->observed_at?->format('Y-m-d H:i') }}</td></tr>@endif @empty<tr><td colspan="4" class="py-3 text-easytire-muted">{{ __('catalog.no_source_prices') }}</td></tr>@endforelse</tbody></table></div></article> @empty <p class="text-sm text-easytire-muted">{{ __('admin.catalog.no_skus') }}</p> @endforelse</div></section>

        @php($primary = $product->mediaAssignments->firstWhere('role', 'primary')?->asset)
        <section class="min-w-0 rounded-2xl border border-easytire-border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">{{ __('media.primary') }}</h2>

            @if ($primary)
                <img src="{{ Storage::disk($primary->disk)->url($primary->path) }}" alt="{{ $primary->alt_nb }}" class="mt-4 aspect-video w-40 rounded-lg border border-easytire-border object-contain p-2">
                <p class="mt-2 max-w-xl break-words text-sm">{{ $primary->alt_nb }}</p>
                <form class="mt-3" method="POST" action="{{ route('admin.media.hero.destroy', ['type' => 'product', 'id' => $product->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="min-h-11 text-sm font-semibold underline decoration-2 underline-offset-4" type="submit">{{ __('media.detach') }}</button>
                </form>
            @endif

            @if ($mediaAssets->isNotEmpty())
                <form class="mt-4 flex min-w-0 flex-col items-start gap-3 sm:flex-row sm:items-end" method="POST" action="{{ route('admin.media.hero.store', ['type' => 'product', 'id' => $product->id]) }}">
                    @csrf
                    <label class="block w-full min-w-0 max-w-xl text-sm font-bold" for="product-primary-media">
                        {{ __('media.asset_label') }}
                        <select id="product-primary-media" name="media_asset_id" required class="easytire-input mt-2 min-h-11 w-full min-w-0 max-w-full truncate rounded-lg px-3">
                            @foreach ($mediaAssets as $asset)
                                <option value="{{ $asset->id }}" @selected($primary?->is($asset))>{{ $asset->original_name }} — {{ $asset->alt_nb }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="min-h-11 shrink-0 rounded-lg border border-easytire-brand px-4 text-sm font-bold text-easytire-brand" type="submit">{{ __('media.attach_replace') }}</button>
                </form>
            @else
                <p class="mt-4 text-sm text-easytire-muted">{{ __('media.no_active') }}</p>
            @endif
        </section>
    </div>
</x-layouts.admin>
