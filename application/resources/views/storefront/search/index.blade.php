@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
@endphp

<x-layouts.storefront :title="__('customer_search.page_title')" :description="__('customer_search.page_description')" :canonical="route($prefix.'site-search.index')">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional et-site-search-page__hero">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ __('customer_search.heading') }}</span></div>
                <p class="et-eyebrow">{{ __('customer_search.kicker') }}</p>
                <h1 class="et-h1">{{ __('customer_search.heading') }}</h1>
                <p class="et-lead">{{ __('customer_search.intro') }}</p>
                <form class="et-site-search-form et-site-search-form--page" method="GET" action="{{ route($prefix.'site-search.index') }}" role="search">
                    <label for="site-search-page-query">{{ __('customer_search.label') }}</label>
                    <div>
                        <input id="site-search-page-query" name="q" type="search" value="{{ $query }}" maxlength="100" autocomplete="off" placeholder="{{ __('customer_search.placeholder') }}">
                        <button type="submit">{{ __('customer_search.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact et-site-search-page" aria-labelledby="search-results-heading">
        <div class="et-container et-site-search-page__layout">
            <div>
                @if ($query !== '')
                    <div class="et-site-search-page__summary">
                        <h2 class="et-h2" id="search-results-heading">{{ __('customer_search.results_heading', ['query' => $query]) }}</h2>
                        <p aria-live="polite">{{ trans_choice('customer_search.result_count', $results->count(), ['count' => $results->count()]) }}</p>
                    </div>

                    @if ($results->isNotEmpty())
                        <ol class="et-site-search-results" data-testid="site-search-results">
                            @foreach ($results as $result)
                                <li>
                                    <a href="{{ $result['url'] }}">
                                        <span class="et-site-search-results__type">{{ __('customer_search.types.'.$result['type']) }}</span>
                                        <strong>{{ $result['title'] }}</strong>
                                        @if ($result['excerpt'] !== '')
                                            <span class="et-site-search-results__excerpt">{{ $result['excerpt'] }}</span>
                                        @endif
                                        <span class="et-site-search-results__action">{{ __('customer_search.open_result') }} <x-icon name="arrow" :size="16" /></span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="et-empty-state" data-testid="site-search-empty">
                            <div><div class="et-empty-state__icon" aria-hidden="true"><x-icon name="search" :size="26" /></div><h2 class="et-h3 et-empty-state__heading">{{ __('customer_search.empty_title') }}</h2><p>{{ __('customer_search.empty_text') }}</p></div>
                            <a class="et-btn et-btn--primary" href="{{ route($prefix.'services.index') }}">{{ __('customer_search.browse_services') }}</a>
                        </div>
                    @endif
                @else
                    <div class="et-site-search-page__summary et-site-search-page__summary--prompt">
                        <h2 class="et-h2" id="search-results-heading">{{ __('customer_search.prompt_title') }}</h2>
                        <p>{{ __('customer_search.prompt_text') }}</p>
                    </div>
                @endif
            </div>

            <aside class="et-site-search-page__finder" aria-label="{{ __('customer_search.tyre_finder_title') }}">
                @include('storefront._quick-finder', ['idPrefix' => 'search-finder', 'routePrefix' => $prefix])
            </aside>
        </div>
    </section>
</x-layouts.storefront>
