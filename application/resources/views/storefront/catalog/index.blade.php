@php
    $isTire = $productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $heading = $isTire ? __('storefront.tires') : __('storefront.wheels');
    $description = $isTire ? __('storefront.tire_description') : __('storefront.wheel_description');
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
@endphp
<x-layouts.storefront :title="__('storefront.catalog_page_title', ['category' => $heading])" :description="$description">
    <section class="border-b border-easytire-border bg-easytire-brand-soft"><div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14"><p class="text-sm font-bold tracking-[.16em] text-easytire-brand uppercase">{{ __('storefront.catalog') }}</p><h1 class="mt-2 text-4xl font-black tracking-tight text-easytire-ink sm:text-5xl">{{ $heading }}</h1><p class="mt-4 max-w-2xl text-lg leading-8 text-easytire-muted">{{ $description }}</p></div></section>
    <div class="mx-auto grid max-w-7xl gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[17rem_1fr] lg:py-12">
        <aside class="h-fit rounded-2xl border border-easytire-border bg-white p-5 shadow-sm" aria-label="{{ __('storefront.filter', ['name' => mb_strtolower($heading)]) }}"><form method="GET" action="{{ route($routePrefix.($isTire ? 'tires.index' : 'wheels.index')) }}" class="space-y-4"><div><label for="q" class="text-sm font-bold">{{ __('storefront.search') }}</label><input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('storefront.search_placeholder') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="brand" class="text-sm font-bold">{{ __('storefront.brand') }}</label><input id="brand" name="brand" value="{{ request('brand') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div>
            @if ($isTire)
                <div class="grid grid-cols-2 gap-3"><div><label for="width" class="text-sm font-bold">{{ __('storefront.width') }}</label><input id="width" name="width" inputmode="decimal" value="{{ request('width') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="aspect" class="text-sm font-bold">{{ __('storefront.aspect') }}</label><input id="aspect" name="aspect" inputmode="decimal" value="{{ request('aspect') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div></div><div><label for="rim" class="text-sm font-bold">{{ __('storefront.rim') }}</label><input id="rim" name="rim" inputmode="decimal" value="{{ request('rim') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="season" class="text-sm font-bold">{{ __('storefront.season') }}</label><select id="season" name="season" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"><option value="">{{ __('storefront.all_seasons') }}</option>@foreach (['Summer' => 'season_summer', 'Winter' => 'season_winter', 'All season' => 'season_all'] as $value => $key)<option value="{{ $value }}" @selected(request('season') === $value)>{{ __('storefront.'.$key) }}</option>@endforeach</select></div>
            @else
                <div class="grid grid-cols-2 gap-3"><div><label for="width" class="text-sm font-bold">{{ __('storefront.width') }}</label><input id="width" name="width" inputmode="decimal" value="{{ request('width') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="diameter" class="text-sm font-bold">{{ __('storefront.diameter') }}</label><input id="diameter" name="diameter" inputmode="decimal" value="{{ request('diameter') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div></div><div><label for="bolt_holes" class="text-sm font-bold">{{ __('storefront.bolts') }}</label><input id="bolt_holes" name="bolt_holes" inputmode="numeric" value="{{ request('bolt_holes') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="pcd" class="text-sm font-bold">{{ __('storefront.pcd') }}</label><input id="pcd" name="pcd" inputmode="decimal" value="{{ request('pcd') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div><div><label for="et" class="text-sm font-bold">{{ __('storefront.offset') }}</label><input id="et" name="et" inputmode="decimal" value="{{ request('et') }}" class="mt-1.5 min-h-11 w-full rounded-lg border border-easytire-border px-3 text-sm"></div>
            @endif
            <div class="flex gap-3 pt-2"><button class="min-h-11 flex-1 rounded-lg bg-easytire-brand px-4 font-bold text-white hover:bg-easytire-brand-strong">{{ __('storefront.show_products') }}</button><a href="{{ route($routePrefix.($isTire ? 'tires.index' : 'wheels.index')) }}" class="grid min-h-11 place-items-center rounded-lg px-2 text-sm font-bold text-easytire-brand underline">{{ __('storefront.reset') }}</a></div></form></aside>
        <section aria-live="polite"><p class="mb-5 text-sm font-semibold text-slate-600">{{ trans_choice('storefront.found', $items->total, ['count' => $items->total]) }}</p>
            @if ($items->items === [])
                <div class="rounded-2xl border border-dashed border-easytire-border bg-white p-8 text-center" data-testid="catalog-empty"><h2 class="text-xl font-black">{{ __('storefront.empty_title') }}</h2><p class="mx-auto mt-3 max-w-lg leading-7 text-easytire-muted">{{ __('storefront.empty_text') }}</p><a href="{{ route($routePrefix.($isTire ? 'tires.index' : 'wheels.index')) }}" class="mt-5 inline-block font-bold text-easytire-brand underline">{{ __('storefront.all_catalog') }}</a></div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-testid="catalog-results">@foreach ($items->items as $item) @include('storefront.catalog._card', ['item' => $item]) @endforeach</div>
                @if ($items->total > $items->perPage)
                    <nav class="mt-8 flex items-center justify-between" aria-label="{{ __('storefront.catalog') }}">
                        @if ($items->page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $items->page - 1]) }}" class="font-bold text-easytire-brand underline">{{ __('storefront.previous') }}</a>
                        @else
                            <span></span>
                        @endif
                        @if ($items->page * $items->perPage < $items->total)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $items->page + 1]) }}" class="font-bold text-easytire-brand underline">{{ __('storefront.next') }}</a>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    </div>
</x-layouts.storefront>
