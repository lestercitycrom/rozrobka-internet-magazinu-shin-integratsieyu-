<x-layouts.admin :title="__('orders.title')">
    <div class="space-y-6">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ __('orders.title') }}</h1>
            <p class="mt-2 text-easytire-muted">{{ __('orders.warning') }}</p>
        </header>

        <form class="grid gap-4 rounded-2xl border border-easytire-border bg-white p-5 md:grid-cols-2 xl:grid-cols-6" method="get">
            <label class="text-sm font-bold md:col-span-2">
                {{ __('orders.reference_or_email') }}
                <input class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="q" type="search" value="{{ $f['q'] ?? '' }}">
            </label>

            <label class="text-sm font-bold">
                {{ __('orders.status') }}
                <select class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="status">
                    <option value="">{{ __('orders.all') }}</option>
                    @foreach (\App\Modules\Orders\Enums\OrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($f['status'] ?? '') === $status->value)>{{ __('order_statuses.'.$status->value) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm font-bold">
                {{ __('orders.payment') }}
                <select class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="payment">
                    <option value="">{{ __('orders.all') }}</option>
                    @foreach (\App\Modules\Orders\Enums\PaymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($f['payment'] ?? '') === $status->value)>{{ __('payment_statuses.'.$status->value) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm font-bold">
                {{ __('orders.ndi') }}
                <select class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="ndi">
                    <option value="">{{ __('orders.all') }}</option>
                    @foreach (\App\Modules\Orders\Enums\NdiOrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($f['ndi'] ?? '') === $status->value)>{{ __('ndi_statuses.'.$status->value) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm font-bold">
                {{ __('orders.from') }}
                <input class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="from" type="date" value="{{ $f['from'] ?? '' }}">
            </label>

            <label class="text-sm font-bold">
                {{ __('orders.to') }}
                <input class="mt-1 min-h-11 w-full rounded-lg border border-easytire-border px-3" name="to" type="date" value="{{ $f['to'] ?? '' }}">
            </label>

            <button class="min-h-11 rounded-lg bg-easytire-ink px-5 font-bold text-white md:self-end" type="submit">{{ __('orders.filter') }}</button>
        </form>

        @if ($orders->isEmpty())
            <p class="rounded-2xl border border-dashed border-easytire-border bg-white p-8 text-easytire-muted">{{ __('orders.empty') }}</p>
        @else
            @php
                $orderTone = ['pending_review' => 'attention', 'awaiting_payment' => 'attention', 'paid' => 'success', 'fulfilment_pending' => 'attention', 'fulfilled' => 'success', 'cancelled' => 'neutral', 'failed' => 'error', 'refunded' => 'neutral'];
                $paymentTone = ['not_started' => 'neutral', 'pending' => 'attention', 'authorized' => 'info', 'capturing' => 'attention', 'partially_paid' => 'info', 'voiding' => 'attention', 'refunding' => 'attention', 'paid' => 'success', 'voided' => 'neutral', 'failed' => 'error', 'partially_refunded' => 'info', 'refunded' => 'neutral'];
                $ndiTone = ['not_submitted' => 'neutral', 'awaiting_approval' => 'attention', 'submitted' => 'info', 'accepted' => 'success', 'rejected' => 'error', 'unknown' => 'neutral'];
            @endphp
            <div class="overflow-x-auto rounded-2xl border border-easytire-border bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-easytire-surface text-easytire-muted">
                        <tr>
                            <th class="p-4">{{ __('orders.reference') }}</th>
                            <th class="p-4">{{ __('orders.status') }}</th>
                            <th class="p-4">{{ __('orders.payment') }}</th>
                            <th class="p-4">{{ __('orders.ndi') }}</th>
                            <th class="p-4">{{ __('order_ui.product_total') }}</th>
                            <th class="p-4">{{ __('order_ui.service_total') }}</th>
                            <th class="p-4">{{ __('orders.total') }}</th>
                            <th class="p-4">{{ __('orders.submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr class="border-t border-easytire-border">
                                <td class="p-4 font-bold"><a class="underline underline-offset-4" href="{{ route('admin.orders.show', $order) }}">{{ $order->public_reference }}</a>@if ($order->is_integration_test)<span class="ml-2 text-xs uppercase text-amber-700">{{ __('order_ui.integration_test_short') }}</span>@endif</td>
                                <td class="p-4"><span class="et-admin-status et-admin-status--{{ $orderTone[$order->status->value] ?? 'neutral' }}">{{ __('order_statuses.'.$order->status->value) }}</span></td>
                                <td class="p-4"><span class="et-admin-status et-admin-status--{{ $paymentTone[$order->payment_status->value] ?? 'neutral' }}">{{ __('payment_statuses.'.$order->payment_status->value) }}</span></td>
                                <td class="p-4"><span class="et-admin-status et-admin-status--{{ $ndiTone[$order->ndi_status->value] ?? 'neutral' }}">{{ __('ndi_statuses.'.$order->ndi_status->value) }}</span></td>
                                <td class="p-4">{{ number_format(($order->product_total_minor ?? 0) / 100, 2, ',', ' ') }} {{ $order->currency }}</td>
                                <td class="p-4">{{ number_format(($order->service_total_minor ?? 0) / 100, 2, ',', ' ') }} {{ $order->currency }}</td>
                                <td class="p-4 font-bold">{{ number_format($order->total_minor / 100, 2, ',', ' ') }} {{ $order->currency }}</td>
                                <td class="p-4">{{ $order->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        @endif
    </div>
</x-layouts.admin>
