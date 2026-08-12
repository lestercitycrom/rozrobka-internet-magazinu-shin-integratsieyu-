<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
use App\Modules\Orders\CreatePendingOrder;
use App\Modules\Orders\Data\CheckoutData;
use App\Modules\Orders\Enums\FulfilmentMethod;
use App\Modules\Orders\Exceptions\CartNotReady;
use App\Modules\Orders\Models\Order;
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

    public function store(CheckoutRequest $request, CartResolver $cart, CreatePendingOrder $orders, SessionCart $session, VehicleLookup $lookup)
    {
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
            $result = $orders->execute(new CheckoutData($key, app()->getLocale(), $request->string('name'), $request->string('email'), $request->string('phone'), FulfilmentMethod::Pickup, $registration, $request->input('note'), $fitmentSnapshot), $cart->resolve());
        } catch (CartNotReady) {
            return redirect()->route($this->routePrefix().'cart.index')
                ->with('cart_error', __('commerce.checkout_not_ready'));
        }
        if ($result->created) {
            $session->clear();
            $request->session()->forget('vehicle_fitment.confirmed');
        }

        return redirect()->route($this->routePrefix().'orders.thanks', $result->order->public_reference);
    }

    public function thanks(string $reference)
    {
        abort_unless(Order::query()->where('public_reference', $reference)->exists(), 404);

        return view('storefront.checkout.thanks', compact('reference'));
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }
}
