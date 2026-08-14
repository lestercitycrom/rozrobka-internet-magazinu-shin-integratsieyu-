@php
    $finderId = $idPrefix ?? 'vehicle-finder';
    $showStoreLink = $showStoreLink ?? true;
@endphp

<div class="et-quick-finder" data-tabs data-testid="quick-finder">
    <div class="et-quick-finder__head">
        <div>
            <span>{{ __('pro.home.finder_kicker') }}</span>
            <h2>{{ __('pro.home.finder_title') }}</h2>
        </div>
        @if ($showStoreLink)
            <a href="{{ route($routePrefix.'store.index') }}">{{ __('pro.home.finder_store_link') }} <x-icon name="arrow" :size="17" /></a>
        @endif
    </div>

    <div class="et-quick-finder__tabs" role="tablist" aria-label="{{ __('pro.home.finder_title') }}">
        <button id="{{ $finderId }}-registration-tab" type="button" role="tab" aria-selected="true" aria-controls="{{ $finderId }}-registration-panel">
            <x-icon name="car" :size="19" />
            {{ __('pro.home.finder_registration_tab') }}
        </button>
        <button id="{{ $finderId }}-size-tab" type="button" role="tab" aria-selected="false" aria-controls="{{ $finderId }}-size-panel" tabindex="-1">
            <x-icon name="tire" :size="19" />
            {{ __('pro.home.finder_size_tab') }}
        </button>
    </div>

    <div class="et-quick-finder__panel" id="{{ $finderId }}-registration-panel" role="tabpanel" aria-labelledby="{{ $finderId }}-registration-tab">
        <form method="POST" action="{{ route($routePrefix.'store.lookup') }}" class="et-quick-finder__registration">
            @csrf
            <label class="sr-only" for="{{ $finderId }}-registration">{{ __('pro.home.registration_label') }}</label>
            <input id="{{ $finderId }}-registration" name="registration" type="text" required maxlength="16" autocomplete="off" autocapitalize="characters" placeholder="{{ __('pro.home.registration_placeholder') }}">
            <button type="submit">{{ __('pro.home.registration_submit') }} <x-icon name="arrow" :size="18" /></button>
        </form>
    </div>

    <div class="et-quick-finder__panel" id="{{ $finderId }}-size-panel" role="tabpanel" aria-labelledby="{{ $finderId }}-size-tab" hidden>
        <form method="GET" action="{{ route($routePrefix.'tires.index') }}" class="et-quick-finder__size">
            <label><span>{{ __('pro.home.finder_width') }}</span><input name="width" type="number" min="100" max="400" inputmode="numeric" placeholder="205" required></label>
            <span aria-hidden="true">/</span>
            <label><span>{{ __('pro.home.finder_aspect') }}</span><input name="aspect" type="number" min="20" max="100" inputmode="numeric" placeholder="55" required></label>
            <span aria-hidden="true">R</span>
            <label><span>{{ __('pro.home.finder_rim') }}</span><input name="rim" type="number" min="10" max="30" inputmode="numeric" placeholder="16" required></label>
            <button type="submit">{{ __('pro.home.finder_size_submit') }} <x-icon name="arrow" :size="18" /></button>
        </form>
    </div>

    <p class="et-quick-finder__note"><x-icon name="check" :size="16" />{{ __('pro.home.finder_note') }}</p>

    <nav class="et-shop-categories" aria-label="{{ __('seo_store.categories_title') }}">
        <a href="{{ route($routePrefix.'tires.index') }}"><x-icon name="tire" :size="22" /><span><strong>{{ __('seo_store.tires_title') }}</strong><small>{{ __('seo_store.explore') }}</small></span></a>
        <a href="{{ route($routePrefix.'wheels.index') }}"><x-icon name="wheel" :size="22" /><span><strong>{{ __('seo_store.wheels_title') }}</strong><small>{{ __('seo_store.explore') }}</small></span></a>
        <a href="{{ route($routePrefix.'requests.create', ['slug' => 'dekk-felg-og-hjulpakker']) }}" data-guided-category><x-icon name="package" :size="22" /><span><strong>{{ __('seo_store.packages_title') }}</strong><small>{{ __('pro.home.guided_choice') }}</small></span></a>
        <a href="{{ route($routePrefix.'requests.create', ['slug' => 'tpms-sensor']) }}" data-guided-category><x-icon name="sensor" :size="22" /><span><strong>{{ __('pro.home.tpms_title') }}</strong><small>{{ __('pro.home.guided_choice') }}</small></span></a>
    </nav>
</div>
