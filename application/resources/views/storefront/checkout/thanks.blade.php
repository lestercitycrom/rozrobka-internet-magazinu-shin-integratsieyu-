<x-layouts.storefront :title="__('commerce.received')" active-section="cart">
    <section class="et-status-page">
        <div class="et-status-card">
            <div class="et-status-icon" aria-hidden="true">✓</div>
            <p class="et-eyebrow">EasyTire</p>
            <h1 class="et-h1" style="margin-inline:auto">{{ __('commerce.received') }}</h1>
            <p class="et-lead" style="margin-inline:auto">{{ __('commerce.pending') }}</p>
            <div class="et-alert et-alert--info" style="margin-top:1.5rem;text-align:left"><span class="et-alert__icon" aria-hidden="true">i</span><div><strong>{{ __('commerce.reference') }}</strong><br>{{ $reference }}</div></div>
        </div>
    </section>
</x-layouts.storefront>
