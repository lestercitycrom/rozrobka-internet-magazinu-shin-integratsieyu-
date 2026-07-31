<x-layouts.admin :title="__('admin.catalog.details')">
    <div class="space-y-8">
        <a class="text-sm underline" href="{{ route('admin.catalog.index') }}">← {{ __('admin.catalog.back') }}</a>
        <header><h1 class="text-3xl font-semibold">{{ $product->title }}</h1><p>{{ $product->brand?->canonical_name }} · {{ $product->product_type->value }}</p></header>
        @php($primary = $product->mediaAssignments->firstWhere('role', 'primary')?->asset)
        <section class="space-y-3"><h2 class="text-lg font-semibold">{{ __('media.primary') }}</h2>
            @if ($primary)
                <img src="{{ Storage::disk($primary->disk)->url($primary->path) }}" alt="{{ $primary->alt_nb }}" class="aspect-video w-64 object-contain">
                <p>{{ $primary->alt_nb }}</p><form method="POST" action="{{ route('admin.media.hero.destroy', ['type'=>'product','id'=>$product->id]) }}">@csrf @method('DELETE')<button class="underline">{{ __('media.detach') }}</button></form>
            @endif
            @if ($mediaAssets->isNotEmpty())<form method="POST" action="{{ route('admin.media.hero.store', ['type'=>'product','id'=>$product->id]) }}">@csrf<select name="media_asset_id" required>@foreach ($mediaAssets as $asset)<option value="{{ $asset->id }}">{{ $asset->original_name }} — {{ $asset->alt_nb }}</option>@endforeach</select><button>{{ __('media.attach_replace') }}</button></form>@else<p>{{ __('media.no_active') }}</p>@endif
        </section>
        <section><h2>{{ __('admin.catalog.skus') }}</h2>@foreach ($product->supplierSkus as $sku)<p>{{ $sku->supplier?->name }} · {{ $sku->external_sku }}</p>@endforeach</section>
    </div>
</x-layouts.admin>
