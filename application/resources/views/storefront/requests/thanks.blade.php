<x-layouts.storefront :title="__('booking.thanks_title')" active-section="services">
    <section class="et-status-page">
        <div class="et-status-card">
            <div class="et-status-icon" aria-hidden="true">✓</div>
            <p class="et-eyebrow">EasyTire</p>
            <h1 class="et-h1" style="margin-inline:auto">{{ __('booking.thanks_title') }}</h1>
            <p class="et-lead" style="margin-inline:auto">{{ __('booking.thanks_text') }}</p>
            <div class="et-alert et-alert--info" style="margin-top:1.5rem;text-align:left"><span class="et-alert__icon" aria-hidden="true">i</span><div><strong>{{ __('booking.reference') }}</strong><br>{{ $reference }}</div></div>
        </div>
    </section>
</x-layouts.storefront>
