<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Exceptions\CartNotReady;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\FinalizeOrderPayment;
use App\Modules\Payments\PrepareOrderPayment;
use App\Modules\VehicleFitment\VehicleLookup;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function create(CartResolver $cart)
    {
        $snapshot = $cart->resolve();
        if (! $snapshot->isCheckoutReady()) {
            return redirect()->route($this->routePrefix().'cart.index')
                ->with('cart_error', __('commerce.checkout_not_ready'));
        }

        $key = session('checkout.key');
        if (! is_string($key) || Order::query()->where('idempotency_key', $key)->exists()) {
            $key = (string) Str::uuid();
            session(['checkout.key' => $key]);
        }

        return view('storefront.checkout.create', [
            'cart' => $snapshot,
            'idempotencyKey' => $key,
            'confirmedFitment' => session('vehicle_fitment.confirmed'),
        ]);
    }

    public function store(
        CheckoutRequest $request,
        CartResolver $cart,
        CreatePendingOrder $orders,
        SessionCart $session,
        VehicleLookup $lookup,
        PrepareOrderPayment $prepare,
        FinalizeOrderPayment $finalize,
    ) {
        $key = (string) session('checkout.key', '');
        abort_unless($key !== '' && hash_equals($key, (string) $request->input('idempotency_key')), 419);
        try {
            $confirmedFitment = $request->session()->get('vehicle_fitment.confirmed');
            $registration = $lookup->normalize((string) $request->input('registration'));
            $fitmentSnapshot = is_array($confirmedFitment)
                && $registration !== null
                && hash_equals((string) ($confirmedFitment['registration'] ?? ''), $registration)
                    ? collect($confirmedFitment)->only(['source', 'width', 'aspect', 'rim', 'confirmed_at'])->all()
                    : null;
            $snapshot = $cart->resolve();
            if ($snapshot->isCheckoutReady() && ! $request->boolean('fitment_confirmation')) {
                return back()->withInput()->withErrors(['fitment_confirmation' => __('commerce.fitment_confirmation_required')]);
            }
            if ($snapshot->isCheckoutReady() && $snapshot->serviceLines !== [] && ! $request->boolean('early_service_start')) {
                return back()->withInput()->withErrors(['early_service_start' => __('commerce.early_service_start_required')]);
            }
            $result = $orders->execute(new CheckoutData(
                idempotencyKey: $key,
                locale: app()->getLocale(),
                customerName: (string) $request->string('name'),
                customerEmail: (string) $request->string('email'),
                customerPhone: (string) $request->string('phone'),
                fulfilmentMethod: FulfilmentMethod::Pickup,
                vehicleRegistration: $registration,
                customerNote: $request->input('note'),
                fitmentSnapshot: $fitmentSnapshot,
                fitmentConfirmed: $request->boolean('fitment_confirmation'),
                paymentObligationConfirmed: true,
                earlyServiceStartRequested: $snapshot->serviceLines !== [] && $request->boolean('early_service_start'),
            ), $snapshot);
        } catch (CartNotReady) {
            return redirect()->route($this->routePrefix().'cart.index')
                ->with('cart_error', __('commerce.checkout_not_ready'));
        }
        if (! config('payments.enabled')) {
            if ($result->created) {
                $session->clear();
                $request->session()->forget('vehicle_fitment.confirmed');
            }

            return redirect()->route($this->routePrefix().'orders.thanks', $result->order->public_reference);
        }

        try {
            $attempt = $prepare->execute(
                $result->order,
                route($this->routePrefix().'payments.dintero.return', $result->order->public_reference),
                route('payments.dintero.callback'),
                route($this->routePrefix().'content.show', 'kjopsvilkar'),
            );
            if ($attempt->status === PaymentStatus::Authorized) {
                $finalize->execute($result->order->refresh());
            }
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['payment' => __('commerce.payment_start_failed')]);
        }

        $redirectUrl = $attempt->metadata_encrypted['redirect_url'] ?? null;
        if (is_string($redirectUrl) && str_starts_with($redirectUrl, 'https://')) {
            return redirect()->away($redirectUrl);
        }

        if ($attempt->status === PaymentStatus::Authorized) {
            $session->clear();
            $request->session()->forget('vehicle_fitment.confirmed');
        }

        return redirect()->route($this->routePrefix().'orders.thanks', $result->order->public_reference);
    }

    public function thanks(string $reference)
    {
        $order = Order::query()->with(['items', 'serviceItems', 'paymentAttempt'])->where('public_reference', $reference)->firstOrFail();

        return view('storefront.checkout.thanks', compact('reference', 'order'));
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }
}
