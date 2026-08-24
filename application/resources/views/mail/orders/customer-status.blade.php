@php($money = fn (int $minor): string => number_format($minor / 100, 2, ',', ' ').' '.$order->currency)
<!doctype html>
<html lang="{{ $order->locale }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('customer_mail.subject_'.$event, ['reference' => $order->public_reference]) }}</title></head>
<body style="margin:0;background:#f4f1ec;color:#101010;font-family:Arial,sans-serif">
<div style="max-width:720px;margin:0 auto;padding:32px 20px">
    <div style="background:#101010;color:#fff;padding:22px 24px"><strong style="font-size:24px">EasyTire</strong><br><span>{{ __('customer_mail.local_team') }}</span></div>
    <div style="background:#fff;border:1px solid #d9d4cc;padding:24px">
        <p style="color:#d90000;font-weight:700;text-transform:uppercase;letter-spacing:.08em">{{ $order->public_reference }}</p>
        <h1 style="font-size:28px;line-height:1.15">{{ __('customer_mail.heading_'.$event) }}</h1>
        <p>{{ __('customer_mail.intro_'.$event) }}</p>

        <h2 style="font-size:18px;margin-top:28px">{{ __('customer_mail.order_summary') }}</h2>
        <table style="width:100%;border-collapse:collapse">
            @foreach($order->items as $item)
                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e1da">{{ $item->product_title }} × {{ $item->quantity }}</td><td style="padding:10px 0;border-bottom:1px solid #e5e1da;text-align:right">{{ $money($item->line_total_minor) }}</td></tr>
            @endforeach
            @foreach($order->serviceItems as $item)
                <tr><td style="padding:10px 0;border-bottom:1px solid #e5e1da">{{ $order->locale === 'ru' ? $item->title_ru : $item->title_nb }} × {{ $item->quantity }}</td><td style="padding:10px 0;border-bottom:1px solid #e5e1da;text-align:right">{{ $money($item->line_total_minor) }}</td></tr>
            @endforeach
            <tr><th style="padding:12px 0;text-align:left">{{ __('customer_mail.total_including_vat') }}</th><th style="padding:12px 0;text-align:right">{{ $money($order->total_minor) }}</th></tr>
            <tr><td>{{ __('customer_mail.vat_included') }}</td><td style="text-align:right">{{ $money((int) round($order->total_minor * 25 / 125)) }}</td></tr>
        </table>

        <p style="margin-top:24px"><strong>{{ __('customer_mail.payment_status') }}:</strong> {{ __('payment_statuses.'.$order->payment_status->value) }}</p>
        @if($order->paymentAttempt?->payment_product_type)<p><strong>{{ __('customer_mail.payment_method') }}:</strong> {{ $order->paymentAttempt->payment_product_type }}@if($order->paymentAttempt->card_last_four) · •••• {{ $order->paymentAttempt->card_last_four }}@endif</p>@endif
        <p>{{ __('customer_mail.authorization_notice') }}</p>

        <h2 style="font-size:18px;margin-top:28px">{{ __('customer_mail.seller') }}</h2>
        <p>EasyTask AS / EasyTire<br>Org.nr. 934 860 187 MVA<br>Ringvegen 200, 9018 Tromsø<br>+47 939 58 270 · max.easytire@gmail.com</p>
        <p><a href="{{ url($order->locale === 'ru' ? '/ru/kjopsvilkar' : '/kjopsvilkar') }}">{{ __('customer_mail.terms') }} ({{ $order->terms_version }})</a> · <a href="{{ url($order->locale === 'ru' ? '/ru/personvern' : '/personvern') }}">{{ __('customer_mail.privacy') }} ({{ $order->privacy_version }})</a> · <a href="{{ url('/dokumenter/angreskjema-easytire.pdf') }}">{{ __('customer_mail.withdrawal_form') }}</a></p>
    </div>
</div>
</body>
</html>
