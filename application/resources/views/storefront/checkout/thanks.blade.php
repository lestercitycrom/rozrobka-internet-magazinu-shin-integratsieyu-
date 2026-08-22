<x-layouts.storefront :title="__('commerce.received')" active-section="cart">
    <section class="et-status-page">
        <div class="et-status-card">
            <div class="et-status-icon" aria-hidden="true">{{ in_array($order->payment_status, [\App\Modules\Orders\Enums\PaymentStatus::Authorized, \App\Modules\Orders\Enums\PaymentStatus::Paid], true) ? '✓' : 'i' }}</div>
            <p class="et-eyebrow">EasyTire</p>
            <h1 class="et-h1" style="margin-inline:auto">{{ __('commerce.received') }}</h1>
            <p class="et-lead" style="margin-inline:auto">{{ __('commerce.payment_result_'.$order->payment_status->value) }}</p>
            <div class="et-alert et-alert--info" style="margin-top:1.5rem;text-align:left"><span class="et-alert__icon" aria-hidden="true">i</span><div><strong>{{ __('commerce.reference') }}</strong><br>{{ $reference }}</div></div>
        </div>
    </section>
</x-layouts.storefront>
