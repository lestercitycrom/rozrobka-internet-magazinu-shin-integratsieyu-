@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $payment = $order->paymentAttempt;
    $vatPercent = (int) config('payments.dintero.vat_percent', 25);
    $vatMinor = $vatPercent > 0 ? (int) round($order->total_minor * $vatPercent / (100 + $vatPercent)) : 0;
@endphp

<x-layouts.storefront :title="__('commerce.receipt')" active-section="cart">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <p class="et-eyebrow">EasyTire</p>
                <h1 class="et-h1">{{ __('commerce.receipt') }}</h1>
                <p class="et-lead">{{ __('commerce.payment_result_'.$order->payment_status->value) }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact" data-testid="order-receipt">
        <div class="et-container et-container--receipt">
            <div class="et-booking-card">
                <div class="et-booking-card__head">
                    <div>
                        <p class="et-caption">{{ __('commerce.reference') }}</p>
                        <h2 class="et-h3">{{ $reference }}</h2>
                    </div>
                    <strong>{{ $order->submitted_at?->timezone('Europe/Oslo')->format('d.m.Y H:i') }}</strong>
                </div>
                <div class="et-booking-card__body">
                    <div class="et-form-grid">
                        <section>
                            <p class="et-eyebrow">{{ __('commerce.receipt_merchant') }}</p>
                            <p class="et-receipt-meta"><strong>EasyTask AS / EasyTire</strong><br>Org.nr. 934 860 187 MVA<br>Ringvegen 200, 9018 Tromsø<br>+47 939 58 270<br>max.easytire@gmail.com</p>
                        </section>
                        <section>
                            <p class="et-eyebrow">{{ __('commerce.receipt_customer') }}</p>
                            <p class="et-receipt-meta"><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_email }}<br>{{ $order->customer_phone }}@if($order->vehicle_registration)<br>{{ __('commerce.registration') }}: {{ $order->vehicle_registration }}@endif</p>
                        </section>
                    </div>

                    <section class="et-receipt-section">
                        <p class="et-eyebrow">{{ __('commerce.receipt_items') }}</p>
                        <div class="et-stack et-receipt-lines">
                            @foreach($order->items as $item)
                                <div class="et-order-summary__row"><span>{{ $item->product_title }} × {{ $item->quantity }}<br><small>{{ __('commerce.sku') }} {{ $item->external_sku }}</small></span><strong><x-storefront-money :minor="$item->line_total_minor" :currency="$order->currency" /></strong></div>
                            @endforeach
                            @foreach($order->serviceItems as $item)
                                <div class="et-order-summary__row"><span>{{ app()->getLocale() === 'ru' ? $item->title_ru : $item->title_nb }} × {{ $item->quantity }}</span><strong><x-storefront-money :minor="$item->line_total_minor" :currency="$order->currency" /></strong></div>
                            @endforeach
                        </div>
                        <dl class="et-receipt-totals">
                            <div class="et-order-summary__row"><dt>{{ __('commerce.vat_included') }}</dt><dd><strong><x-storefront-money :minor="$vatMinor" :currency="$order->currency" /></strong></dd></div>
                            <div class="et-order-summary__row"><dt>{{ __('commerce.mandatory_fees') }}</dt><dd><strong><x-storefront-money :minor="0" :currency="$order->currency" /></strong></dd></div>
                            <div class="et-order-summary__total"><dt>{{ __('commerce.cart_total') }}</dt><dd><x-storefront-money :minor="$order->total_minor" :currency="$order->currency" /></dd></div>
                        </dl>
                    </section>

                    <div class="et-form-grid et-receipt-section">
                        <section data-testid="receipt-payment">
                            <p class="et-eyebrow">{{ __('commerce.receipt_payment') }}</p>
                            <p class="et-receipt-meta"><strong>{{ __('payment_statuses.'.$order->payment_status->value) }}</strong></p>
                            @if($payment?->provider_reference)<p class="et-help">{{ __('commerce.dintero_transaction') }}: <span class="et-breakable-reference">{{ $payment->provider_reference }}</span></p>@endif
                            @if($payment?->payment_product_type)<p class="et-help">{{ __('commerce.payment_method') }}: {{ $payment->payment_product_type }}</p>@endif
                            @if($payment?->card_last_four)<p class="et-help">{{ __('commerce.card_ending', ['digits' => $payment->card_last_four]) }}</p>@endif
                            @if(($payment?->captured_amount_minor ?? 0) > 0)<p class="et-help">{{ __('payment_statuses.paid') }}: <x-storefront-money :minor="$payment->captured_amount_minor" :currency="$order->currency" /></p>@endif
                            @if(($payment?->refunded_amount_minor ?? 0) > 0)<p class="et-help">{{ __('payment_statuses.refunded') }}: <x-storefront-money :minor="$payment->refunded_amount_minor" :currency="$order->currency" /></p>@endif
                        </section>
                        <section>
                            <p class="et-eyebrow">{{ __('commerce.receipt_delivery') }}</p>
                            <p class="et-receipt-meta">{{ __('commerce.fulfilment_summary') }}<br><strong>Ringvegen 200, 9018 Tromsø</strong></p>
                        </section>
                    </div>

                    <section class="et-receipt-section" data-testid="receipt-legal-evidence">
                        <p class="et-eyebrow">{{ __('commerce.receipt_legal') }}</p>
                        <p class="et-receipt-meta"><a class="et-btn--link" href="{{ route($prefix.'content.show', 'kjopsvilkar') }}">{{ __('commerce.terms_version', ['version' => $order->terms_version]) }}</a><br><a class="et-btn--link" href="{{ route($prefix.'content.show', 'personvern') }}">{{ __('commerce.privacy_version', ['version' => $order->privacy_version]) }}</a></p>
                        <p class="et-receipt-download"><a class="et-btn et-btn--ghost et-btn--small" href="/dokumenter/angreskjema-easytire.pdf" download>{{ __('commerce.withdrawal_form') }}</a></p>
                    </section>
                </div>
            </div>
        </div>
    </section>
</x-layouts.storefront>
