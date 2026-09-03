@php
    $isTire = $productType === \App\Modules\Catalog\Enums\ProductType::Tire;
    $routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $heading = $isTire ? __('storefront.tires') : __('storefront.wheels');
    $description = $isTire ? __('storefront.tire_description') : __('storefront.wheel_description');
    $routeName = $routePrefix.($isTire ? 'tires.index' : 'wheels.index');
    $suggestionsRoute = $routePrefix.($isTire ? 'tires.suggestions' : 'wheels.suggestions');
    $baseUrl = route($routeName);
    $categoryItems = [
        [
            'key' => 'tires',
            'icon' => 'tire',
            'href' => route($routePrefix.'tires.index'),
            'title' => __('catalog_correction.tires_title'),
            'text' => __('catalog_correction.tires_text'),
            'active' => $isTire,
            'request' => false,
        ],
        [
            'key' => 'wheels',
            'icon' => 'wheel',
            'href' => route($routePrefix.'wheels.index'),
            'title' => __('catalog_correction.wheels_title'),
            'text' => __('catalog_correction.wheels_text'),
            'active' => ! $isTire,
            'request' => false,
        ],
        [
            'key' => 'packages',
            'icon' => 'package',
            'href' => route($routePrefix.'requests.create', ['slug' => 'dekk-felg-og-hjulpakker']),
            'title' => __('catalog_correction.packages_title'),
            'text' => __('catalog_correction.packages_text'),
            'active' => false,
            'request' => true,
        ],
        [
            'key' => 'tpms',
            'icon' => 'sensor',
            'href' => route($routePrefix.'requests.create', ['slug' => 'tpms-sensor']),
            'title' => __('catalog_correction.tpms_title'),
            'text' => __('catalog_correction.tpms_text'),
            'active' => false,
            'request' => true,
        ],
    ];
    $query = $filters;
    $filterLabels = [
        'q' => __('storefront.search'),
        'brand' => __('storefront.brand'),
        'width' => __('storefront.width'),
        'aspect' => __('storefront.aspect'),
        'rim' => __('storefront.rim'),
        'season' => __('storefront.season'),
        'diameter' => __('storefront.diameter'),
        'bolt_holes' => __('storefront.bolt_holes'),
        'pcd' => __('storefront.pcd'),
        'et' => __('storefront.offset'),
        'center_bore' => __('storefront.center_bore'),
        'in_stock' => __('storefront.in_stock'),
        'has_image' => __('storefront.has_image'),
        'sort' => __('storefront.sort'),
    ];
    $activeFilters = collect($query)
        ->only(array_keys($filterLabels))
        ->filter(fn ($value) => $value !== null && $value !== '' && $value !== '0');
    $matchesValue = fn (string $field, string $value): bool => rtrim(rtrim(str_replace(',', '.', $query[$field] ?? ''), '0'), '.') === rtrim(rtrim($value, '0'), '.');
    $formatOption = fn (string $value): string => rtrim(rtrim($value, '0'), '.');
    $seasonLabel = fn (string $value): string => match (strtolower($value)) {
        'summer' => __('storefront.season_summer'),
        'winter' => __('storefront.season_winter'),
        'all season', 'all-season', 'allseason' => __('storefront.season_all'),
        default => $value,
    };
@endphp

<x-layouts.storefront :title="__('storefront.catalog_page_title', ['category' => $heading])" :description="$description" active-section="store" :store-route="$baseUrl" :store-label="$heading">
    <section class="et-v3-catalog-hero">
        <div class="et-container et-catalog-shell et-v3-catalog-hero__inner">
            <div>
                <div class="et-breadcrumbs"><span><a href="{{ route($routePrefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $heading }}</span></div>
                <p class="et-eyebrow">{{ __('pro.catalog.kicker') }}</p>
                <h1 class="et-h1">{{ $heading }}</h1>
                <p>{{ $description }}</p>
            </div>
            <div class="et-v3-catalog-hero__note">
                <x-icon name="check" :size="20" />
                <span>{{ __('pro.catalog.compatibility') }}</span>
            </div>
        </div>
    </section>

    <section class="et-v3-catalog-section">
        <nav class="et-container et-catalog-shell et-catalog-categories" aria-label="{{ __('catalog_correction.categories_label') }}" data-testid="catalog-category-rail">
            <p class="sr-only">{{ __('catalog_correction.categories_help') }}</p>
            <div class="et-catalog-categories__rail">
                @foreach ($categoryItems as $category)
                    <a
                        class="et-catalog-category {{ $category['active'] ? 'is-active' : '' }} {{ $category['request'] ? 'is-request' : '' }}"
                        href="{{ $category['href'] }}"
                        @if ($category['active']) aria-current="page" @endif
                        data-testid="catalog-category-{{ $category['key'] }}"
                    >
                        <span class="et-catalog-category__icon"><x-icon :name="$category['icon']" :size="22" /></span>
                        <span class="et-catalog-category__copy">
                            <strong>{{ $category['title'] }}</strong>
                            <small>{{ $category['text'] }}</small>
                        </span>
                        @if ($category['request'])
                            <span class="et-catalog-category__badge">{{ __('catalog_correction.request_badge') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            <p class="et-catalog-categories__note"><x-icon name="check" :size="16" />{{ __('catalog_correction.request_note') }}</p>
        </nav>

        @php
            $fitmentContext = session('vehicle_fitment.context');
        @endphp
        @if (! $isTire && is_array($fitmentContext) && ($fitmentContext['intent'] ?? null) === 'wheels')
            <div class="et-container et-catalog-shell">
                <div class="et-alert et-alert--info et-fitment-context-notice" data-testid="fitment-context-notice">
                    <span class="et-alert__icon" aria-hidden="true">i</span>
                    <div>{{ __('fitment.wheels_manual_notice', ['vehicle' => $fitmentContext['vehicle'] ?: $fitmentContext['registration'], 'size' => $fitmentContext['size']]) }}</div>
                </div>
            </div>
        @endif

        <div class="et-container et-catalog-shell et-catalog-layout" data-catalog-live-root aria-busy="false">
            <aside aria-label="{{ __('storefront.filter', ['name' => mb_strtolower($heading)]) }}">
                <details class="et-production-filter" open data-mobile-filter data-testid="catalog-filter-drawer">
                    <summary data-testid="catalog-filter-toggle">
                        <span><x-icon name="filter" :size="19" />{{ __('pro.catalog.filters') }}</span>
                        @if ($activeFilters->isNotEmpty())<span class="et-filter-counter">{{ $activeFilters->count() }}</span>@endif
                    </summary>
                    <div class="et-production-filter__panel">
                        <div class="et-production-filter__head">
                            <div>
                                <p class="et-eyebrow">{{ __('pro.catalog.controls') }}</p>
                                <h2 class="et-h3">{{ __('pro.catalog.filters') }}</h2>
                            </div>
                            <button class="et-production-filter__close" type="button" aria-label="{{ __('pro.catalog.close_filters') }}" data-filter-details-close data-testid="catalog-filter-close">×</button>
                        </div>

                        <form method="GET" action="{{ $baseUrl }}" data-testid="catalog-filters" data-catalog-live-form>
                            @if (isset($query['sort']))<input type="hidden" name="sort" value="{{ $query['sort'] }}">@endif
                            <div class="et-production-filter__body">
                                <details class="et-filter-section et-filter-group et-filter-section--search" open data-testid="catalog-filter-group-search">
                                    <summary class="et-filter-group__summary"><span><b>00</b>{{ __('catalog_correction.search_group') }}</span></summary>
                                    <div class="et-filter-group__body">
                                    <div class="et-field">
                                        <label class="et-label" for="q">{{ __('storefront.search') }}</label>
                                        <div class="et-filter-search"><x-icon name="search" :size="18" /><input class="et-input" id="q" name="q" type="search" value="{{ $query['q'] ?? '' }}" placeholder="{{ __('storefront.search_placeholder') }}" list="catalog-suggestions" autocomplete="off" aria-controls="catalog-suggestions" data-catalog-autocomplete="{{ route($suggestionsRoute) }}"></div>
                                        <datalist id="catalog-suggestions"></datalist>
                                    </div>

                                    <div class="et-field">
                                        <label class="et-label" for="brand">{{ __('storefront.brand') }}</label>
                                        <select class="et-select" id="brand" name="brand">
                                            <option value="">{{ __('storefront.all_brands') }}</option>
                                            @foreach ($filterOptions['brands'] as $brand)
                                                <option value="{{ $brand }}" @selected(($query['brand'] ?? null) === $brand)>{{ $brand }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    </div>
                                </details>

                                @if ($isTire)
                                    <details class="et-filter-section et-filter-group" open data-testid="catalog-filter-group-dimensions">
                                        <summary class="et-filter-group__summary"><span><b>01</b>{{ __('pro.catalog.dimensions') }}</span></summary>
                                        <div class="et-filter-group__body">
                                        <div class="et-size-builder et-size-builder--tire">
                                        @foreach (['width' => 'widths', 'aspect' => 'aspects'] as $field => $optionKey)
                                            <div class="et-field">
                                                <label class="et-label" for="{{ $field }}">{{ $filterLabels[$field] }}</label>
                                                <select class="et-select" id="{{ $field }}" name="{{ $field }}">
                                                    <option value="">{{ __('storefront.any') }}</option>
                                                    @foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ $formatOption($value) }}</option>@endforeach
                                                </select>
                                            </div>
                                            @if ($field === 'width')<span class="et-size-builder__separator" aria-hidden="true">/</span>@endif
                                        @endforeach
                                        <span class="et-size-builder__separator" aria-hidden="true">R</span>
                                        <div class="et-field">
                                            <label class="et-label" for="rim">{{ __('storefront.rim_short') }}</label>
                                            <select class="et-select" id="rim" name="rim"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions['rims'] as $value)<option value="{{ $value }}" @selected($matchesValue('rim', $value))>{{ $formatOption($value) }}</option>@endforeach</select>
                                        </div>
                                        </div>
                                        <div class="et-field">
                                            <label class="et-label" for="season">{{ __('storefront.season') }}</label>
                                            <select class="et-select" id="season" name="season"><option value="">{{ __('storefront.all_seasons') }}</option>@foreach ($filterOptions['seasons'] as $value)<option value="{{ $value }}" @selected(($query['season'] ?? null) === $value)>{{ $seasonLabel($value) }}</option>@endforeach</select>
                                        </div>
                                        </div>
                                    </details>
                                @else
                                    <details class="et-filter-section et-filter-group" open data-testid="catalog-filter-group-wheel-size">
                                        <summary class="et-filter-group__summary"><span><b>01</b>{{ __('pro.catalog.wheel_size') }}</span></summary>
                                        <div class="et-filter-group__body">
                                        <div class="et-form-grid">
                                        @foreach (['width' => 'widths', 'diameter' => 'diameters'] as $field => $optionKey)
                                            <div class="et-field"><label class="et-label" for="{{ $field }}">{{ $filterLabels[$field] }}</label><select class="et-select" id="{{ $field }}" name="{{ $field }}"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ $formatOption($value) }}</option>@endforeach</select></div>
                                        @endforeach
                                        </div>
                                        </div>
                                    </details>
                                    <details class="et-filter-section et-filter-group" open data-testid="catalog-filter-group-fitment">
                                        <summary class="et-filter-group__summary"><span><b>02</b>{{ __('pro.catalog.fitment') }}</span></summary>
                                        <div class="et-filter-group__body">
                                        <div class="et-form-grid">
                                        @foreach (['bolt_holes' => 'bolt_holes', 'pcd' => 'pcds'] as $field => $optionKey)
                                            <div class="et-field"><label class="et-label" for="{{ $field }}">{{ $filterLabels[$field] }}</label><select class="et-select" id="{{ $field }}" name="{{ $field }}"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ $formatOption($value) }}</option>@endforeach</select></div>
                                        @endforeach
                                        </div>
                                        <div class="et-form-grid">
                                        @foreach (['et' => 'ets', 'center_bore' => 'center_bores'] as $field => $optionKey)
                                            <div class="et-field"><label class="et-label" for="{{ $field }}">{{ $filterLabels[$field] }}</label><select class="et-select" id="{{ $field }}" name="{{ $field }}"><option value="">{{ __('storefront.any') }}</option>@foreach ($filterOptions[$optionKey] as $value)<option value="{{ $value }}" @selected($matchesValue($field, $value))>{{ $formatOption($value) }}</option>@endforeach</select></div>
                                        @endforeach
                                        </div>
                                        </div>
                                    </details>
                                @endif

                                <details class="et-filter-section et-filter-group et-filter-availability" open data-testid="catalog-filter-group-availability">
                                    <summary class="et-filter-group__summary"><span><b>{{ $isTire ? '02' : '03' }}</b>{{ __('pro.catalog.availability') }}</span></summary>
                                    <div class="et-filter-group__body">
                                        <label class="et-check et-filter-toggle"><input type="checkbox" name="in_stock" value="1" @checked(($query['in_stock'] ?? null) === '1')><span>{{ __('storefront.in_stock') }}</span></label>
                                        <label class="et-check et-filter-toggle"><input type="checkbox" name="has_image" value="1" @checked(($query['has_image'] ?? null) === '1')><span>{{ __('storefront.has_image') }}</span></label>
                                    </div>
                                </details>
                            </div>

                            <div class="et-production-filter__footer">
                                <p class="et-filter-request-error" role="alert" aria-live="assertive" data-catalog-filter-error data-testid="catalog-filter-error" hidden>
                                    <span class="et-filter-request-error__icon" aria-hidden="true">!</span>
                                    <span>{{ __('storefront.filter_request_failed') }}</span>
                                </p>
                                <a class="et-btn et-btn--outline" href="{{ $baseUrl }}" data-catalog-live-link data-testid="catalog-filter-reset">{{ __('storefront.reset') }}</a>
                                <button class="et-btn et-btn--primary" type="submit" data-testid="catalog-filter-apply" aria-busy="false">
                                    <span data-catalog-apply-label>{{ __('storefront.show_products') }}</span>
                                    <span class="et-filter-apply-busy" data-catalog-apply-busy hidden><span class="et-catalog-loading-dot" aria-hidden="true"></span>{{ __('storefront.applying_filters') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </details>
            </aside>

            <section class="et-catalog-results" aria-live="polite" data-catalog-live-results>
                <div class="et-toolbar">
                    <div class="et-toolbar__group">
                        <p class="et-result-count">{{ trans_choice('storefront.found', $items->total, ['count' => $items->total]) }}</p>
                        <span class="et-loading-line" data-catalog-loading role="status" aria-live="polite" aria-hidden="true"><span class="et-catalog-loading-dot" aria-hidden="true"></span>{{ __('storefront.loading_results') }}</span>
                    </div>
                    <form method="GET" action="{{ $baseUrl }}" class="et-toolbar__group et-catalog-sort" data-catalog-live-sort>
                        @foreach ($query as $key => $value)@if ($key !== 'sort' && $key !== 'page')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                        <label class="et-label et-catalog-sort__label" for="sort">{{ __('storefront.sort') }}</label>
                        <select class="et-select" id="sort" name="sort">
                            <option value="">{{ __('storefront.sort_recommended') }}</option>
                            <option value="title" @selected(($query['sort'] ?? null) === 'title')>{{ __('storefront.sort_title') }}</option>
                            <option value="brand" @selected(($query['sort'] ?? null) === 'brand')>{{ __('storefront.sort_brand') }}</option>
                        </select>
                    </form>
                </div>

                @if ($activeFilters->isNotEmpty())
                    <div class="et-toolbar__group et-active-filters" data-testid="active-filters">
                        <span class="et-caption"><strong>{{ __('storefront.active_filters') }}</strong></span>
                        @foreach ($activeFilters as $key => $value)
                            @php
                                $without = collect($query)->except([$key, 'page'])->all();
                                $displayValue = match ($key) {
                                    'in_stock', 'has_image' => __('storefront.yes'),
                                    'season' => $seasonLabel($value),
                                    'sort' => match ($value) {
                                        'title' => __('storefront.sort_title'),
                                        'brand' => __('storefront.sort_brand'),
                                        default => $value,
                                    },
                                    default => $value,
                                };
                            @endphp
                            <a class="et-chip et-chip--active" href="{{ $baseUrl.(count($without) ? '?'.http_build_query($without) : '') }}" data-catalog-live-link>
                                {{ $filterLabels[$key] }}: {{ $displayValue }} <span aria-hidden="true">×</span><span class="sr-only">{{ __('storefront.remove_filter') }}</span>
                            </a>
                        @endforeach
                        <a class="et-btn et-btn--link et-btn--small" href="{{ $baseUrl }}" data-catalog-live-link data-testid="catalog-filter-reset-all">{{ __('storefront.reset_all') }}</a>
                    </div>
                @endif

                @if ($items->items === [])
                    <div class="et-empty-state" data-testid="catalog-empty">
                        <div>
                            <div class="et-empty-state__icon" aria-hidden="true">⌕</div>
                            <h2 class="et-h3 et-empty-state__heading">{{ __('storefront.empty_title') }}</h2>
                            <p>{{ __('storefront.empty_text') }}</p>
                            <a class="et-btn et-btn--primary" href="{{ $baseUrl }}" data-catalog-live-link>{{ __('storefront.all_catalog') }}</a>
                        </div>
                    </div>
                @else
                    <div class="et-product-grid" data-testid="catalog-results">
                        @foreach ($items->items as $item)
                            @include('storefront.catalog._card', ['item' => $item, 'sellingPrice' => $sellingPrices->get($item->sku)])
                        @endforeach
                    </div>

                    @if ($items->total > $items->perPage)
                        <nav class="et-toolbar et-catalog-pagination" aria-label="{{ __('storefront.catalog') }}">
                            @if ($items->page > 1)
                                <a class="et-btn et-btn--outline" href="{{ $baseUrl.'?'.http_build_query([...$query, 'page' => $items->page - 1]) }}" data-catalog-live-link>{{ __('storefront.previous') }}</a>
                            @else<span></span>@endif
                            @if ($items->page * $items->perPage < $items->total)
                                <a class="et-btn et-btn--outline" href="{{ $baseUrl.'?'.http_build_query([...$query, 'page' => $items->page + 1]) }}" data-catalog-live-link>{{ __('storefront.next') }}</a>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>
        </div>
    </section>
</x-layouts.storefront>
