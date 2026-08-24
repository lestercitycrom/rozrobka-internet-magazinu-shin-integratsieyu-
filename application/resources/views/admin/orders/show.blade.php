@php($money = fn (int $minor): string => number_format($minor / 100, 2, ',', ' ').' '.$order->currency)

<x-layouts.admin :title="$order->public_reference">
    <div class="max-w-6xl space-y-8">
        <header>
            <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.orders.index') }}">{{ __('orders.title') }}</a>
            <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $order->public_reference }}</h1>
            @if ($order->is_integration_test)
                <p class="mt-3 inline-flex border border-amber-400 bg-amber-50 px-3 py-2 text-sm font-black text-amber-950" data-testid="admin-integration-test-order">{{ __('order_ui.integration_test_order') }}</p>
            @endif
            <p class="mt-3 text-easytire-muted">{{ __('order_ui.order_workflow_warning') }}</p>
        </header>

        @if ($errors->has('payment'))
            <div class="border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900" role="alert">{{ $errors->first('payment') }}</div>
        @endif

        @if ($payment = $order->paymentAttempt)
            <section class="easytire-card p-5" data-testid="admin-order-payment">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.16em] text-easytire-brand">{{ __('order_ui.payment_operations') }}</p>
                        <h2 class="mt-2 text-xl font-black">{{ __('payment_statuses.'.$payment->status->value) }}</h2>
                    </div>
                    @if ($payment->provider_reference)
                        <form method="post" action="{{ route('admin.orders.payment.reconcile', $order) }}">
                            @csrf
                            <button class="min-h-11 border border-easytire-border bg-white px-4 text-sm font-black hover:border-easytire-brand" type="submit">{{ __('order_ui.reconcile') }}</button>
                        </form>
                    @endif
                </div>
                <dl class="mt-5 grid gap-4 border-t border-easytire-border pt-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-easytire-muted">{{ __('order_ui.payment_provider') }}</dt><dd class="mt-1 font-bold">{{ $payment->provider }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.payment_session') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ $payment->provider_session_reference ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.payment_transaction') }}</dt><dd class="mt-1 break-all font-mono text-xs">{{ $payment->provider_reference ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.goods_ready') }}</dt><dd class="mt-1 font-bold">{{ $order->goods_ready_at ?: __('order_ui.goods_not_ready') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.payment_method') }}</dt><dd class="mt-1 font-bold">{{ $payment->payment_product_type ?: __('order_ui.not_available') }}@if($payment->card_last_four) · •••• {{ $payment->card_last_four }}@endif</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.authorization_expires') }}</dt><dd class="mt-1 font-bold">{{ $payment->authorization_expires_at ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.captured_amount') }}</dt><dd class="mt-1 font-bold">{{ $money($payment->captured_amount_minor) }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.refunded_amount') }}</dt><dd class="mt-1 font-bold">{{ $money($payment->refunded_amount_minor) }}</dd></div>
                </dl>
                @if ($payment->status === \App\Modules\Orders\Enums\PaymentStatus::Authorized && $payment->authorizationExpired())
                    <div class="mt-5 border border-red-400 bg-red-50 p-4 text-sm font-black text-red-950" role="alert">{{ __('order_ui.authorization_expired') }}</div>
                @elseif ($payment->status === \App\Modules\Orders\Enums\PaymentStatus::Authorized && $payment->authorization_expires_at?->isBefore(now()->addDays(2)))
                    <div class="mt-5 border border-amber-400 bg-amber-50 p-4 text-sm font-black text-amber-950" role="alert">{{ __('order_ui.authorization_expiring') }}</div>
                @endif

                @if ($order->status === \App\Modules\Orders\Enums\OrderStatus::AwaitingPayment && $order->payment_status === \App\Modules\Orders\Enums\PaymentStatus::Authorized && $order->ndi_status === \App\Modules\Orders\Enums\NdiOrderStatus::Accepted && ! $payment->authorizationExpired())
                    <form class="mt-5 border-t border-easytire-border pt-5" method="post" action="{{ route('admin.orders.payment.capture', $order) }}" data-testid="admin-capture-payment">
                        @csrf
                        <p class="max-w-3xl text-sm text-easytire-muted">{{ __('order_ui.capture_help') }}</p>
                        <fieldset class="mt-4 border border-easytire-border p-4">
                            <legend class="px-2 text-sm font-black">{{ __('order_ui.capture_lines') }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($order->items as $item)
                                    <label class="grid grid-cols-[1fr_5rem] items-center gap-3 text-sm"><span>{{ $item->product_title }} <span class="text-easytire-muted">({{ __('order_ui.max_quantity', ['quantity' => $item->quantity]) }})</span></span><input class="easytire-input min-h-11 w-full px-3" type="number" min="0" max="{{ $item->quantity }}" name="items[product-{{ $item->id }}]" value="{{ old('items.product-'.$item->id, $item->quantity) }}"></label>
                                @endforeach
                                @foreach ($order->serviceItems as $item)
                                    <label class="grid grid-cols-[1fr_5rem] items-center gap-3 text-sm"><span>{{ app()->getLocale() === 'ru' ? $item->title_ru : $item->title_nb }} <span class="text-easytire-muted">({{ __('order_ui.max_quantity', ['quantity' => $item->quantity]) }})</span></span><input class="easytire-input min-h-11 w-full px-3" type="number" min="0" max="{{ $item->quantity }}" name="items[service-{{ $item->id }}]" value="{{ old('items.service-'.$item->id, $item->quantity) }}"></label>
                                @endforeach
                            </div>
                            <label class="mt-4 flex items-start gap-3 text-sm"><input class="mt-1 size-5" type="checkbox" name="customer_agreement" value="1"><span>{{ __('order_ui.partial_capture_agreement') }}</span></label>
                        </fieldset>
                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                            <label class="text-sm font-bold">{{ __('order_ui.goods_ready_note') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="note" required maxlength="1000" value="{{ old('note', $order->goods_ready_note) }}"></label>
                            <button class="min-h-11 bg-easytire-brand px-5 text-sm font-black text-white sm:self-end" type="submit">{{ __('order_ui.capture_now') }}</button>
                        </div>
                    </form>
                @endif

                @if ($order->payment_status === \App\Modules\Orders\Enums\PaymentStatus::Authorized && in_array($order->ndi_status, [\App\Modules\Orders\Enums\NdiOrderStatus::NotSubmitted, \App\Modules\Orders\Enums\NdiOrderStatus::Rejected], true))
                    <form class="mt-5 border-t border-easytire-border pt-5" method="post" action="{{ route('admin.orders.payment.void', $order) }}">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                            <label class="text-sm font-bold">{{ __('order_ui.goods_ready_note') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="note" required maxlength="1000"></label>
                            <button class="min-h-11 border border-easytire-border bg-white px-5 text-sm font-black hover:border-easytire-brand sm:self-end" type="submit">{{ __('order_ui.void') }}</button>
                        </div>
                    </form>
                @endif

                @if (in_array($payment->status, [\App\Modules\Orders\Enums\PaymentStatus::Paid, \App\Modules\Orders\Enums\PaymentStatus::PartiallyPaid, \App\Modules\Orders\Enums\PaymentStatus::PartiallyRefunded], true) && $payment->refundableMinor() > 0)
                    @php($capturedOperation = $payment->operations->where('type', \App\Modules\Payments\Enums\PaymentOperationType::Capture)->where('status', \App\Modules\Payments\Enums\PaymentOperationStatus::Completed)->sortByDesc('started_at')->first())
                    @php($capturedQuantities = collect($capturedOperation?->items_encrypted ?? [])->mapWithKeys(fn ($line) => [$line['line_id'] => (int) $line['quantity']]))
                    @php($refundedQuantities = $payment->operations->where('type', \App\Modules\Payments\Enums\PaymentOperationType::Refund)->where('status', \App\Modules\Payments\Enums\PaymentOperationStatus::Completed)->flatMap(fn ($operation) => $operation->items_encrypted ?? [])->groupBy('line_id')->map(fn ($lines) => $lines->sum('quantity')))
                    <form class="mt-5 border-t border-easytire-border pt-5" method="post" action="{{ route('admin.orders.payment.refund', $order) }}" data-testid="admin-refund-payment">
                        @csrf
                        <p class="max-w-3xl text-sm text-easytire-muted">{{ __('order_ui.refund_help') }}</p>
                        <fieldset class="mt-4 border border-easytire-border p-4">
                            <legend class="px-2 text-sm font-black">{{ __('order_ui.refund_lines') }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($order->items as $item)
                                    @php($lineId = 'product-'.$item->id)
                                    @php($remaining = max(0, (int) $capturedQuantities->get($lineId, 0) - (int) $refundedQuantities->get($lineId, 0)))
                                    @if($remaining > 0)<label class="grid grid-cols-[1fr_5rem] items-center gap-3 text-sm"><span>{{ $item->product_title }} <span class="text-easytire-muted">({{ __('order_ui.max_quantity', ['quantity' => $remaining]) }})</span></span><input class="easytire-input min-h-11 w-full px-3" type="number" min="0" max="{{ $remaining }}" name="items[{{ $lineId }}]" value="0"></label>@endif
                                @endforeach
                                @foreach ($order->serviceItems as $item)
                                    @php($lineId = 'service-'.$item->id)
                                    @php($remaining = max(0, (int) $capturedQuantities->get($lineId, 0) - (int) $refundedQuantities->get($lineId, 0)))
                                    @if($remaining > 0)<label class="grid grid-cols-[1fr_5rem] items-center gap-3 text-sm"><span>{{ app()->getLocale() === 'ru' ? $item->title_ru : $item->title_nb }} <span class="text-easytire-muted">({{ __('order_ui.max_quantity', ['quantity' => $remaining]) }})</span></span><input class="easytire-input min-h-11 w-full px-3" type="number" min="0" max="{{ $remaining }}" name="items[{{ $lineId }}]" value="0"></label>@endif
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                            <label class="text-sm font-bold">{{ __('order_ui.refund_reason') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="note" required maxlength="1000"></label>
                            <button class="min-h-11 bg-easytire-brand px-5 text-sm font-black text-white sm:self-end" type="submit">{{ __('order_ui.refund_now') }}</button>
                        </div>
                    </form>
                @endif

                @if ($payment->operations->isNotEmpty())
                    <div class="mt-5 border-t border-easytire-border pt-5">
                        <h3 class="font-black">{{ __('order_ui.operation_log') }}</h3>
                        <div class="mt-3 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr><th class="p-2">{{ __('order_ui.operation') }}</th><th class="p-2">{{ __('orders.status') }}</th><th class="p-2">{{ __('order_ui.amount') }}</th><th class="p-2">{{ __('order_ui.operator') }}</th><th class="p-2">{{ __('orders.note') }}</th><th class="p-2">{{ __('order_ui.started') }}</th></tr></thead><tbody class="divide-y divide-easytire-border">@foreach($payment->operations->sortByDesc('started_at') as $operation)<tr><td class="p-2 font-bold">{{ __('order_ui.operation_'.$operation->type->value) }}</td><td class="p-2">{{ __('order_ui.operation_status_'.$operation->status->value) }}</td><td class="p-2">{{ $money($operation->amount_minor) }}</td><td class="p-2">{{ $operation->actor?->email ?: __('order_ui.system') }}</td><td class="p-2">{{ $operation->note }}</td><td class="p-2">{{ $operation->started_at }}</td></tr>@endforeach</tbody></table></div>
                    </div>
                @endif
            </section>
        @endif

        @if ($attempt = $order->ndiOrderAttempt)
            <section class="easytire-card p-5">
                <h2 class="font-black">NDI</h2>
                <p class="mt-3 text-sm">{{ $attempt->status->value }} · {{ $attempt->sales_status ?: __('order_ui.not_available') }} · {{ $attempt->document_status ?: __('order_ui.not_available') }}</p>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-easytire-muted">{{ __('order_ui.ndi_reference') }}</dt><dd class="mt-1 font-bold">{{ $attempt->external_order_reference ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.carrier') }}</dt><dd class="mt-1">{{ $attempt->carrier ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.status_checked') }}</dt><dd class="mt-1">{{ $attempt->status_checked_at ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.despatch_checked') }}</dt><dd class="mt-1">{{ $attempt->despatch_checked_at ?: __('order_ui.not_available') }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('order_ui.error_code') }}</dt><dd class="mt-1">{{ $attempt->error_code ?: __('order_ui.not_available') }}</dd></div>
                </dl>
                @php($trackingItems = is_array($attempt->tracking_summary_encrypted) ? ($attempt->tracking_summary_encrypted['tracking'] ?? []) : [])
                @if ($trackingItems)
                    <div class="mt-5 border-t border-easytire-border pt-5">
                        <h3 class="font-bold">{{ __('order_ui.tracking') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($trackingItems as $tracking)
                                @php($number = (string) ($tracking['number'] ?? ''))
                                @php($url = $tracking['url'] ?? null)
                                <li>
                                    <span>{{ str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4) }}</span>
                                    @if (is_string($url) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))
                                        <a class="ml-2 underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $url }}">{{ __('order_ui.tracking') }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        <section class="easytire-card overflow-x-auto">
            <div class="border-b border-easytire-border p-5"><h2 class="text-lg font-black">{{ __('order_ui.products') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('order_ui.immutable_snapshot') }}</p></div>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-easytire-surface text-easytire-muted"><tr><th class="p-4">{{ __('order_ui.products') }}</th><th class="p-4">{{ __('order_ui.quantity') }}</th><th class="p-4">{{ __('order_ui.unit_price') }}</th><th class="p-4">{{ __('order_ui.line_total') }}</th></tr></thead>
                <tbody class="divide-y divide-easytire-border">
                    @foreach ($order->items as $item)
                        <tr><td class="p-4 font-bold">{{ $item->product_title }}@if (($item->product_snapshot['is_integration_test'] ?? false) === true)<span class="ml-2 text-xs uppercase text-amber-700">{{ __('order_ui.integration_test_short') }}</span>@endif</td><td class="p-4">{{ $item->quantity }}</td><td class="p-4">{{ $money($item->unit_price_minor) }}</td><td class="p-4 font-bold">{{ $money($item->line_total_minor) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="easytire-card overflow-x-auto">
            <div class="border-b border-easytire-border p-5"><h2 class="text-lg font-black">{{ __('order_ui.services') }}</h2></div>
            @if ($order->serviceItems->isEmpty())
                <p class="p-5 text-sm text-easytire-muted">{{ __('order_ui.no_services') }}</p>
            @else
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-easytire-surface text-easytire-muted"><tr><th class="p-4">{{ __('order_ui.services') }}</th><th class="p-4">{{ __('order_ui.quantity') }}</th><th class="p-4">{{ __('order_ui.unit_price') }}</th><th class="p-4">{{ __('order_ui.line_total') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @foreach ($order->serviceItems as $item)
                            <tr><td class="p-4 font-bold">{{ app()->getLocale() === 'ru' ? $item->title_ru : $item->title_nb }}</td><td class="p-4">{{ $item->quantity }}</td><td class="p-4">{{ $money($item->unit_price_minor) }}</td><td class="p-4 font-bold">{{ $money($item->line_total_minor) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="easytire-card p-5">
            <h2 class="text-lg font-black">{{ __('orders.totals') }}</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-easytire-muted">{{ __('order_ui.product_total') }}</dt><dd class="mt-1 font-black">{{ $money($order->items->sum('line_total_minor')) }}</dd></div>
                <div><dt class="text-easytire-muted">{{ __('order_ui.service_total') }}</dt><dd class="mt-1 font-black">{{ $money($order->serviceItems->sum('line_total_minor')) }}</dd></div>
                <div><dt class="text-easytire-muted">{{ __('order_ui.order_total') }}</dt><dd class="mt-1 text-xl font-black text-easytire-brand">{{ $money($order->total_minor) }}</dd></div>
            </dl>
        </section>

        @if ($allowed !== [])
            <section class="easytire-card p-5">
                <h2 class="text-lg font-black">{{ __('order_ui.transition') }}</h2>
                <p class="mt-1 text-sm text-easytire-muted">{{ __('order_ui.transition_help') }}</p>
                <form class="mt-5 grid gap-4 sm:grid-cols-[1fr_2fr_auto]" method="post" action="{{ route('admin.orders.transition', $order) }}">
                    @csrf
                    <label class="text-sm font-bold">{{ __('orders.status') }}<select name="status" class="easytire-input mt-2 min-h-11 w-full px-3">@foreach ($allowed as $status)<option value="{{ $status->value }}">{{ __('order_statuses.'.$status->value) }}</option>@endforeach</select></label>
                    <label class="text-sm font-bold">{{ __('orders.note') }}<input name="note" required class="easytire-input mt-2 min-h-11 w-full px-3"></label>
                    <button class="min-h-11 bg-easytire-brand px-5 text-sm font-bold text-white sm:self-end" type="submit">{{ __('orders.update') }}</button>
                </form>
            </section>
        @endif

        <section class="easytire-card p-5">
            <h2 class="text-lg font-black">{{ __('orders.history') }}</h2>
            @foreach ($order->statusHistory as $history)
                <p class="mt-3 text-sm">{{ __('order_statuses.'.$history->to_status->value) }} {{ $history->note }}</p>
            @endforeach
        </section>
    </div>
</x-layouts.admin>
