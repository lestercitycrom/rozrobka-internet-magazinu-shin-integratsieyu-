<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartLineRequest;
use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;

class CartController extends Controller
{
    public function index(CartResolver $cart)
    {
        return view('storefront.cart.index', ['cart' => $cart->resolve()]);
    }

    public function add(CartLineRequest $r, SessionCart $cart)
    {
        $cart->add($r->string('sku')->toString(), $r->integer('quantity'));

        return back();
    }

    public function update(CartLineRequest $r, SessionCart $cart)
    {
        $cart->put($r->string('sku')->toString(), $r->integer('quantity'));

        return back();
    }

    public function remove(CartLineRequest $r, SessionCart $cart)
    {
        $cart->remove($r->string('sku')->toString());

        return back();
    }
}
