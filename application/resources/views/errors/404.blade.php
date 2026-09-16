@php($errorPrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')
<x-layouts.storefront :title="__('errors.title')">
    <section class="et-error-page">
        <div class="et-container et-error-page__inner">
            <span class="et-error-page__code" aria-hidden="true">404</span>
            <div>
                <p class="et-eyebrow">EasyTire</p>
                <h1 class="et-h1">{{ __('errors.heading') }}</h1>
                <p class="et-lead">{{ __('errors.not_found_message') }}</p>
                <div class="et-page-actions">
                    <a class="et-btn et-btn--primary" href="{{ route($errorPrefix.'home') }}">{{ __('errors.home') }}</a>
                    <a class="et-action-link" href="{{ route($errorPrefix.'tires.index') }}"><span class="et-action-link__label">{{ __('storefront.catalog') }}</span><x-icon name="arrow" :size="16" /></a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.storefront>
