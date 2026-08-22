<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Cart\SessionCart;
use App\Modules\NdiApi\Exceptions\NdiAvailabilityNotConfirmed;
use App\Modules\NdiApi\Exceptions\NdiOrderNotReady;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Dintero\DinteroCallbackSignature;
use App\Modules\Payments\FinalizeOrderPayment;
use App\Modules\Payments\SynchronizeProviderPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class DinteroPaymentController extends Controller
{
    public function callback(
        Request $request,
        DinteroCallbackSignature $signature,
        SynchronizeProviderPayment $synchronize,
        FinalizeOrderPayment $finalize,
    ): Response {
        abort_unless($signature->isValid($request), 401);
        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
            'session_id' => ['required', 'string', 'max:255'],
            'merchant_reference' => ['required', 'string', 'max:32'],
            'time' => ['required', 'string', 'max:64'],
        ]);
        $order = Order::query()->where('public_reference', $data['merchant_reference'])->firstOrFail();
        $attempt = $synchronize->execute($order, $data['transaction_id'], 'dintero_callback');
        if ($attempt->status === PaymentStatus::Authorized) {
            try {
                $finalize->execute($order->refresh());
            } catch (NdiAvailabilityNotConfirmed|NdiOrderNotReady) {
                // The coordinator has already voided a definitive unavailable/rejected order
                // or persisted an uncertain NDI state for manual reconciliation.
            }
        }

        return response('', 204);
    }

    public function return(
        Request $request,
        string $reference,
        SynchronizeProviderPayment $synchronize,
        FinalizeOrderPayment $finalize,
        SessionCart $cart,
    ): RedirectResponse {
        $order = Order::query()->where('public_reference', $reference)->firstOrFail();
        $transactionId = trim((string) $request->query('transaction_id'));
        if ($transactionId !== '') {
            try {
                $attempt = $synchronize->execute($order, $transactionId, 'dintero_return');
                if ($attempt->status === PaymentStatus::Authorized) {
                    try {
                        $finalize->execute($order->refresh());
                    } catch (NdiAvailabilityNotConfirmed|NdiOrderNotReady) {
                        // See callback: the safe state is already persisted.
                    }
                }
            } catch (Throwable) {
                // Browser return is informational only. The reliable callback will retry.
            }
        }

        $order->refresh();
        if (in_array($order->payment_status, [PaymentStatus::Authorized, PaymentStatus::Paid], true)) {
            $cart->clear();
            $request->session()->forget(['checkout.key', 'vehicle_fitment.confirmed']);
        }

        return redirect()->route($this->routePrefix().'orders.thanks', $order->public_reference);
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }
}
