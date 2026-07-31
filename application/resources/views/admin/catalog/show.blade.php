<x-layouts.admin :title="__('admin.catalog.details')">
    <div class="space-y-8">
        <a class="text-sm underline" href="{{ route('admin.catalog.index') }}">← {{ __('admin.catalog.back') }}</a>
        <header><h1 class="text-3xl font-semibold">{{ $product->title }}</h1><p>{{ $product->brand?->canonical_name }} · {{ $product->product_type->value }}</p></header>
        @if(session('status'))<p class="border-l-4 border-easytire-brand bg-easytire-brand-soft p-3">{{ session('status') }}</p>@endif
        <section class="space-y-3"><h2 class="text-lg font-semibold">{{ __('catalog.publication') }}</h2><form method="POST" action="{{ route('admin.catalog.publication.update', $product) }}" class="flex flex-wrap items-end gap-3">@csrf @method('PUT')<label class="text-sm font-medium">{{ __('admin.catalog.status') }}<select name="publication_status" class="mt-1 block border border-easytire-border bg-white px-3 py-2">@foreach($publicationStatuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $product->publication_status->value) === $status->value)>{{ __('catalog.statuses.'.$status->value) }}</option>@endforeach</select></label><button class="bg-easytire-brand px-4 py-2 text-sm font-semibold text-white">{{ __('catalog.update_publication') }}</button></form>@error('publication_status')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</section>
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
