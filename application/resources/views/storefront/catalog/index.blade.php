@php
    $isTire = $productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $heading = $isTire ? __('storefront.tires') : __('storefront.wheels');
    $description = $isTire ? __('storefront.tire_description') : __('storefront.wheel_description');
    $routeName = $routePrefix.($isTire ? 'tires.index' : 'wheels.index');
    $suggestionsRoute = $routePrefix.($isTire ? 'tires.suggestions' : 'wheels.suggestions');
    $baseUrl = route($routeName);
    $query = $filters;
    $filterLabels = [
        'q' => __('storefront.search'), 'brand' => __('storefront.brand'), 'width' => __('storefront.width'),
        'aspect' => __('storefront.aspect'), 'rim' => __('storefront.rim'), 'season' => __('storefront.season'),
        'diameter' => __('storefront.diameter'), 'bolt_holes' => __('storefront.bolt_holes'), 'pcd' => __('storefront.pcd'),
        'et' => __('storefront.offset'), 'center_bore' => __('storefront.center_bore'), 'in_stock' => __('storefront.in_stock'),
        'has_image' => __('storefront.has_image'), 'sort' => __('storefront.sort'),
    ];
    $activeFilters = collect($query)->only(array_keys($filterLabels))->filter(fn ($value) => $value !== null && $value !== '' && $value !== '0');
    $matchesValue = fn (string $field, string $value): bool => rtrim(rtrim(str_replace(',', '.', $query[$field] ?? ''), '0'), '.') === rtrim(rtrim($value, '0'), '.');
    $seasonLabel = fn (string $value): string => match (strtolower($value)) {
        'summer' => __('storefront.season_summer'),
        'winter' => __('storefront.season_winter'),
        'all season', 'all-season', 'allseason' => __('storefront.season_all'),
        default => $value,
        };
@endphp

<x-layouts.storefront :title="__('storefront.catalog_page_title', ['category' => $heading])" :description="$description">
    <section class="border-b border-easytire-border bg-easytire-brand-soft">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14">
            <p class="text-sm font-bold tracking-[.16em] text-easytire-brand uppercase">{{ __('storefront.catalog') }}</p>
            <h1 class="mt-2 text-4xl font-black tracking-tight text-easytire-ink sm:text-5xl">{{ $heading }}</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-easytire-muted">{{ $description }}</p>
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[18rem_minmax(0,1fr)] lg:py-12" data-catalog-live-root aria-busy="false">
        <aside class="h-fit lg:sticky lg:top-5" aria-label="{{ __('storefront.filter', ['name' => mb_strtolower($heading)]) }}">
            <details class="group overflow-hidden rounded-2xl border border-easytire-border bg-white shadow-sm" open data-mobile-filter>
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 lg:cursor-default lg:pointer-events-none">
                    <span class="font-black">{{ __('storefront.filter', ['name' => mb_strtolower($heading)]) }}</span>
                    <span class="rounded-full bg-easytire-brand-soft px-2.5 py-1 text-xs font-bold text-easytire-brand lg:hidden">{{ $activeFilters->count() }}</span>
                </summary>
                <form method="GET" action="{{ $baseUrl }}" class="space-y-5 border-t border-easytire-border px-5 py-5" data-testid="catalog-filters" data-catalog-live-form>
                    @if (isset($query['sort']))<input type="hidden" name="sort" value="{{ $query['sort'] }}">@endif
                    <div>
                        <label for="q" class="text-sm font-bold">{{ __('storefront.search') }}</label>
                        <input id="q" name="q" type="search" value="{{ $query['q'] ?? '' }}" placeholder="{{ __('storefront.search_placeholder') }}" list="catalog-suggestions" autocomplete="off" aria-controls="catalog-suggestions" data-catalog-autocomplete="{{ route($suggestionsRoute) }}" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm">
                        <datalist id="catalog-suggestions"></datalist>
                    </div>
                    <div>
                        <label for="brand" class="text-sm font-bold">{{ __('storefront.brand') }}</label>
                        <select id="brand" name="brand" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.all_brands') }}</option>@foreach ($filterOptions['brands'] as $brand)<option value="{{ $brand }}" @selected(($query['brand'] ?? null) === $brand)>{{ $brand }}</option>@endforeach</select>
                    </div>
                    @if ($isTire)
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['width' => 'widths', 'aspect' => 'aspects'] as $field => $optionKey)
                                <div><label for="{{ $field }}" class="text-sm font-bold">{{ $filterLabels[$field] }}</label><select id="{{ $field }}" name="{{ $field }}" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ rtrim(rtrim($value, '0'), '.') }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label for="rim" class="text-sm font-bold">{{ __('storefront.rim') }}</label><select id="rim" name="rim" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions['rims'] as $value)<option value="{{ $value }}" @selected($matchesValue('rim', $value))>{{ rtrim(rtrim($value, '0'), '.') }}</option>@endforeach</select></div>
                            <div><label for="season" class="text-sm font-bold">{{ __('storefront.season') }}</label><select id="season" name="season" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.all_seasons') }}</option>@foreach ($filterOptions['seasons'] as $value)<option value="{{ $value }}" @selected(($query['season'] ?? null) === $value)>{{ $seasonLabel($value) }}</option>@endforeach</select></div>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['width' => 'widths', 'diameter' => 'diameters'] as $field => $optionKey)
                                <div><label for="{{ $field }}" class="text-sm font-bold">{{ $filterLabels[$field] }}</label><select id="{{ $field }}" name="{{ $field }}" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ rtrim(rtrim($value, '0'), '.') }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['bolt_holes' => 'bolt_holes', 'pcd' => 'pcds'] as $field => $optionKey)
                                <div><label for="{{ $field }}" class="text-sm font-bold">{{ $filterLabels[$field] }}</label><select id="{{ $field }}" name="{{ $field }}" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ rtrim(rtrim($value, '0'), '.') }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['et' => 'ets', 'center_bore' => 'center_bores'] as $field => $optionKey)
                                <div><label for="{{ $field }}" class="text-sm font-bold">{{ $filterLabels[$field] }}</label><select id="{{ $field }}" name="{{ $field }}" class="easytire-input mt-1.5 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ rtrim(rtrim($value, '0'), '.') }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                    @endif
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-easytire-surface px-3 py-3 text-sm font-bold"><input type="checkbox" name="in_stock" value="1" @checked(($query['in_stock'] ?? null) === '1') class="size-4 rounded border-easytire-border text-easytire-brand focus:ring-easytire-brand">{{ __('storefront.in_stock') }}</label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-easytire-surface px-3 py-3 text-sm font-bold"><input type="checkbox" name="has_image" value="1" @checked(($query['has_image'] ?? null) === '1') class="size-4 rounded border-easytire-border text-easytire-brand focus:ring-easytire-brand">{{ __('storefront.has_image') }}</label>
                    <div class="flex gap-3 pt-1"><button class="min-h-11 flex-1 rounded-lg bg-easytire-brand px-4 text-sm font-bold text-white transition-colors hover:bg-easytire-brand-strong">{{ __('storefront.show_products') }}</button><a href="{{ $baseUrl }}" class="grid min-h-11 place-items-center rounded-lg px-2 text-sm font-bold text-easytire-brand underline" data-catalog-live-link>{{ __('storefront.reset') }}</a></div>
                </form>
            </details>
        </aside>
        <section aria-live="polite" data-catalog-live-results>
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-semibold text-easytire-muted">{{ trans_choice('storefront.found', $items->total, ['count' => $items->total]) }}</p>
                    <span class="inline-flex items-center gap-2 text-sm font-bold text-easytire-brand transition-opacity" data-catalog-loading role="status" aria-live="polite" aria-hidden="true"><span class="size-2 rounded-full bg-easytire-brand" aria-hidden="true"></span>{{ __('storefront.loading_results') }}</span>
                </div>
                <form method="GET" action="{{ $baseUrl }}" class="flex items-center gap-2" data-catalog-live-sort>@foreach ($query as $key => $value) @if ($key !== 'sort' && $key !== 'page')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label for="sort" class="text-sm font-bold">{{ __('storefront.sort') }}</label><select id="sort" name="sort" class="easytire-input min-h-10 rounded-lg px-3 text-sm"><option value="">{{ __('storefront.sort_recommended') }}</option><option value="title" @selected(($query['sort'] ?? null) === 'title')>{{ __('storefront.sort_title') }}</option><option value="brand" @selected(($query['sort'] ?? null) === 'brand')>{{ __('storefront.sort_brand') }}</option></select></form>
            </div>
            @if ($activeFilters->isNotEmpty())
                <div class="mb-6 flex flex-wrap items-center gap-2" data-testid="active-filters"><span class="text-sm font-bold">{{ __('storefront.active_filters') }}</span>@foreach ($activeFilters as $key => $value) @php
                    $without = collect($query)->except([$key, 'page'])->all();
                    $displayValue = match ($key) {
                        'in_stock' => __('storefront.yes'),
                        'has_image' => __('storefront.yes'),
                        'season' => $seasonLabel($value),
                        'sort' => match ($value) {
                            'title' => __('storefront.sort_title'),
                            'brand' => __('storefront.sort_brand'),
                            default => $value,
                        },
                        default => $value,
                    };
                @endphp<a href="{{ $baseUrl.(count($without) ? '?'.http_build_query($without) : '') }}" class="inline-flex items-center gap-1 rounded-full border border-easytire-border bg-white px-3 py-1.5 text-sm font-semibold transition-colors hover:border-easytire-brand hover:text-easytire-brand" data-catalog-live-link>{{ $filterLabels[$key] }}: {{ $displayValue }} <span aria-hidden="true">x</span><span class="sr-only">{{ __('storefront.remove_filter') }}</span></a>@endforeach<a href="{{ $baseUrl }}" class="text-sm font-bold text-easytire-brand underline" data-catalog-live-link>{{ __('storefront.reset_all') }}</a></div>
            @endif
            @if ($items->items === [])
                <div class="rounded-2xl border border-dashed border-easytire-border bg-white p-8 text-center" data-testid="catalog-empty"><h2 class="text-xl font-black">{{ __('storefront.empty_title') }}</h2><p class="mx-auto mt-3 max-w-lg leading-7 text-easytire-muted">{{ __('storefront.empty_text') }}</p><a href="{{ $baseUrl }}" class="mt-5 inline-block font-bold text-easytire-brand underline" data-catalog-live-link>{{ __('storefront.all_catalog') }}</a></div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-testid="catalog-results">@foreach ($items->items as $item) @include('storefront.catalog._card', ['item' => $item, 'sellingPrice' => $sellingPrices->get($item->sku)]) @endforeach</div>
                @if ($items->total > $items->perPage)<nav class="mt-8 flex items-center justify-between" aria-label="{{ __('storefront.catalog') }}">@if ($items->page > 1)<a href="{{ $baseUrl.'?'.http_build_query([...$query, 'page' => $items->page - 1]) }}" class="font-bold text-easytire-brand underline" data-catalog-live-link>{{ __('storefront.previous') }}</a>@else<span></span>@endif @if ($items->page * $items->perPage < $items->total)<a href="{{ $baseUrl.'?'.http_build_query([...$query, 'page' => $items->page + 1]) }}" class="font-bold text-easytire-brand underline" data-catalog-live-link>{{ __('storefront.next') }}</a>@endif</nav>@endif
            @endif
        </section>
    </div>
</x-layouts.storefront>
