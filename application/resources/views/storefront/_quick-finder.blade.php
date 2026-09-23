@php
    $finderId = $idPrefix ?? 'vehicle-finder';
    $showStoreLink = $showStoreLink ?? true;
    $dimensionOptions = config('storefront.tyre_dimensions', ['widths' => [], 'aspects' => [], 'rims' => []]);
    $selectedWidth = request()->query('width');
    $selectedAspect = request()->query('aspect');
    $selectedRim = request()->query('rim');
@endphp

<div class="et-quick-finder" data-tabs data-testid="quick-finder">
    <div class="et-quick-finder__head">
        @if (config('storefront.design_profile') === 'classic')
            <div>
                <span>{{ __('pro.home.finder_kicker') }}</span>
                <h2>{{ __('pro.home.finder_title') }}</h2>
            </div>
        @else
            <h2>{{ __('pro.home.finder_title') }}</h2>
        @endif
        @if ($showStoreLink)
            <a class="et-action-link et-action-link--small" href="{{ route($routePrefix.'store.index') }}"><span class="et-action-link__label">{{ __('pro.home.finder_store_link') }}</span><x-icon name="arrow" :size="16" /></a>
        @endif
    </div>

    <div class="et-quick-finder__tabs" role="tablist" aria-label="{{ __('pro.home.finder_title') }}">
        <button id="{{ $finderId }}-registration-tab" type="button" role="tab" aria-label="{{ __('pro.home.finder_registration_tab') }}" aria-selected="true" aria-controls="{{ $finderId }}-registration-panel">
            <x-icon name="car" :size="19" />
            <span class="et-quick-finder__tab-full" aria-hidden="true">{{ __('pro.home.finder_registration_tab') }}</span>
            <span class="et-quick-finder__tab-compact" aria-hidden="true">{{ __('pro.home.finder_registration_tab_compact') }}</span>
        </button>
        <button id="{{ $finderId }}-size-tab" type="button" role="tab" aria-label="{{ __('pro.home.finder_size_tab') }}" aria-selected="false" aria-controls="{{ $finderId }}-size-panel" tabindex="-1">
            <x-icon name="tire" :size="19" />
            <span class="et-quick-finder__tab-full" aria-hidden="true">{{ __('pro.home.finder_size_tab') }}</span>
            <span class="et-quick-finder__tab-compact" aria-hidden="true">{{ __('pro.home.finder_size_tab_compact') }}</span>
        </button>
    </div>

    <div class="et-quick-finder__panel" id="{{ $finderId }}-registration-panel" role="tabpanel" aria-labelledby="{{ $finderId }}-registration-tab">
        <form method="POST" action="{{ route($routePrefix.'store.lookup') }}" class="et-quick-finder__registration">
            @csrf
            <label class="sr-only" for="{{ $finderId }}-registration">{{ __('pro.home.registration_label') }}</label>
            <input id="{{ $finderId }}-registration" name="registration" type="text" required maxlength="16" autocomplete="off" autocapitalize="characters" placeholder="{{ __('pro.home.registration_placeholder') }}">
            <button class="et-btn et-btn--primary" type="submit">{{ __('pro.home.registration_submit') }} <x-icon name="arrow" :size="16" /></button>
        </form>
    </div>

    <div class="et-quick-finder__panel" id="{{ $finderId }}-size-panel" role="tabpanel" aria-labelledby="{{ $finderId }}-size-tab" hidden>
        <form method="GET" action="{{ route($routePrefix.'tires.index') }}" class="et-quick-finder__size">
            <label for="{{ $finderId }}-width"><span>{{ __('pro.home.finder_width') }}</span><select id="{{ $finderId }}-width" name="width" required data-testid="finder-width"><option value="">{{ __('pro.home.finder_select_placeholder') }}</option>@foreach ($dimensionOptions['widths'] as $value)<option value="{{ $value }}" @selected((string) $selectedWidth === (string) $value)>{{ $value }}</option>@endforeach</select></label>
            <span aria-hidden="true">/</span>
            <label for="{{ $finderId }}-aspect"><span>{{ __('pro.home.finder_aspect') }}</span><select id="{{ $finderId }}-aspect" name="aspect" required data-testid="finder-aspect"><option value="">{{ __('pro.home.finder_select_placeholder') }}</option>@foreach ($dimensionOptions['aspects'] as $value)<option value="{{ $value }}" @selected((string) $selectedAspect === (string) $value)>{{ $value }}</option>@endforeach</select></label>
            <span aria-hidden="true">R</span>
            <label for="{{ $finderId }}-rim"><span>{{ __('pro.home.finder_rim') }}</span><select id="{{ $finderId }}-rim" name="rim" required data-testid="finder-rim"><option value="">{{ __('pro.home.finder_select_placeholder') }}</option>@foreach ($dimensionOptions['rims'] as $value)<option value="{{ $value }}" @selected((string) $selectedRim === (string) $value)>{{ $value }}</option>@endforeach</select></label>
            <button class="et-btn et-btn--primary" type="submit">{{ __('pro.home.finder_size_submit') }} <x-icon name="arrow" :size="16" /></button>
        </form>
    </div>

    <p class="et-quick-finder__note">@if (config('storefront.design_profile') === 'classic')<x-icon name="check" :size="16" />@endif{{ __('pro.home.finder_note') }}</p>

    <nav class="et-shop-categories" aria-label="{{ __('seo_store.categories_title') }}">
        <a href="{{ route($routePrefix.'tires.index') }}"><x-icon name="tire" :size="20" /><span><strong>{{ __('seo_store.tires_title') }}</strong><small class="et-shop-categories__catalog-hint">{{ __('seo_store.explore') }}</small></span></a>
        <a href="{{ route($routePrefix.'wheels.index') }}"><x-icon name="wheel" :size="20" /><span><strong>{{ __('seo_store.wheels_title') }}</strong><small class="et-shop-categories__catalog-hint">{{ __('seo_store.explore') }}</small></span></a>
        <a href="{{ route($routePrefix.'requests.create', ['slug' => 'dekk-felg-og-hjulpakker']) }}" data-guided-category><x-icon name="package" :size="20" /><span><strong>{{ __('seo_store.packages_title') }}</strong><small>{{ __('pro.home.guided_choice') }}</small></span></a>
        <a href="{{ route($routePrefix.'requests.create', ['slug' => 'tpms-sensor']) }}" data-guided-category><x-icon name="sensor" :size="20" /><span><strong>{{ __('pro.home.tpms_title') }}</strong><small>{{ __('pro.home.guided_choice') }}</small></span></a>
    </nav>
</div>
