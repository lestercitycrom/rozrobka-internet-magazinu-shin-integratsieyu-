<x-layouts.storefront :title="__('booking.thanks_title')" active-section="services">
    <section class="et-status-page">
        <div class="et-status-card">
            <div class="et-status-icon" aria-hidden="true">✓</div>
            <p class="et-eyebrow">EasyTire</p>
            <h1 class="et-h1 et-status-card__heading">{{ __('booking.thanks_title') }}</h1>
            <p class="et-lead et-status-card__lead">{{ __('booking.thanks_text') }}</p>
            <div class="et-alert et-alert--info et-status-card__reference"><span class="et-alert__icon" aria-hidden="true">i</span><div><strong>{{ __('booking.reference') }}</strong><br>{{ $reference }}</div></div>
        </div>
    </section>
</x-layouts.storefront>
