<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\CaptureAuthorizedPayment;
use App\Modules\Payments\FinalizeOrderPayment;
use App\Modules\Payments\PaymentIdempotencyKey;
use App\Modules\Payments\RefundCapturedPayment;
use App\Modules\Payments\SynchronizeProviderPayment;
use App\Modules\Payments\VoidAuthorizedPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OrderPaymentController
{
    public function capture(Request $request, Order $order, CaptureAuthorizedPayment $capture): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'customer_agreement' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $order, $data): void {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless(
                $locked->status === OrderStatus::AwaitingPayment
                && $locked->payment_status === PaymentStatus::Authorized
                && $locked->ndi_status === NdiOrderStatus::Accepted,
                409,
            );
            if ($locked->goods_ready_at === null) {
                $locked->update([
                    'goods_ready_at' => now(),
                    'goods_ready_by_user_id' => $request->user()->id,
                    'goods_ready_note' => trim($data['note']),
                ]);
                $locked->statusHistory()->create([
                    'actor_user_id' => $request->user()->id,
                    'from_status' => $locked->status,
                    'to_status' => $locked->status,
                    'note' => trim($data['note']),
                    'metadata' => ['source' => 'admin_goods_ready'],
                    'occurred_at' => now(),
                ]);
            }
        }, 3);

        try {
            $capture->execute(
                $order->refresh(),
                PaymentIdempotencyKey::captureFor($order),
                $data['items'] ?? null,
                $request->user()->id,
                trim($data['note']),
                $request->boolean('customer_agreement'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => __('order_ui.capture_pending_reconcile')]);
        }

        return back()->with('status', __('order_ui.capture_completed'));
    }

    public function refund(Request $request, Order $order, RefundCapturedPayment $refund): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $attempt = $order->paymentAttempt()->firstOrFail();
        $key = PaymentIdempotencyKey::refundFor($order, $attempt->refunded_amount_minor, $data['items']);

        try {
            $refund->execute(
                $order,
                $key,
                $data['items'],
                $request->user()->id,
                trim($data['note']),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => __('order_ui.refund_pending_reconcile')]);
        }

        return back()->with('status', __('order_ui.refund_completed'));
    }

    public function reconcile(Order $order, SynchronizeProviderPayment $synchronize, FinalizeOrderPayment $finalize): RedirectResponse
    {
        $attempt = $order->paymentAttempt()->first();
        abort_if($attempt === null || $attempt->provider_reference === null, 409);

        try {
            $attempt = $synchronize->execute($order, $attempt->provider_reference, 'admin_reconcile');
            if ($attempt->status === PaymentStatus::Authorized && $order->refresh()->ndi_status === NdiOrderStatus::NotSubmitted) {
                $finalize->execute($order);
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => __('order_ui.reconcile_failed')]);
        }

        return back()->with('status', __('order_ui.reconciled'));
    }

    public function void(Request $request, Order $order, VoidAuthorizedPayment $void): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        abort_unless(in_array($order->ndi_status, [NdiOrderStatus::NotSubmitted, NdiOrderStatus::Rejected], true), 409);

        try {
            $void->execute($order, PaymentIdempotencyKey::voidFor($order), $request->user()->id, trim($data['note']));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => __('order_ui.void_failed')]);
        }

        return back()->with('status', __('order_ui.void_completed'));
    }
}
