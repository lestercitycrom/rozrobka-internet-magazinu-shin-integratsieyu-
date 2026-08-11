<x-layouts.storefront :title="__('errors.title')">
    <section class="et-status-page">
        <div class="et-status-card">
            <div class="et-status-icon" style="background:var(--et-red-soft);color:var(--et-red)" aria-hidden="true">!</div>
            <p class="et-eyebrow">EasyTire</p>
            <h1 class="et-h1" style="margin-inline:auto">{{ __('errors.heading') }}</h1>
            <p class="et-lead" style="margin-inline:auto">{{ __('errors.message') }}</p>
            <a class="et-btn et-btn--primary" href="{{ app()->getLocale() === 'ru' ? '/ru' : '/' }}" style="margin-top:1.5rem">{{ __('errors.home') }}</a>
        </div>
    </section>
</x-layouts.storefront>
