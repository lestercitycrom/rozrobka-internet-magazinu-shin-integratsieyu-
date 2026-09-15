<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartLineRequest;
use App\Http\Requests\Storefront\CartServiceAddonRequest;
use App\Modules\Cart\CartResolver;
use App\Modules\Cart\SessionCart;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\NdiApi\NdiSandboxContract;
use App\Modules\Pricing\SellingPriceService;

class CartController extends Controller
{
    public function index(CartResolver $cart)
    {
        return view('storefront.cart.index', ['cart' => $cart->resolve()]);
    }

    public function add(CartLineRequest $r, SessionCart $cart, CatalogReadService $catalog, SellingPriceService $pricing)
    {
        $sku = $r->string('sku')->toString();
        $quantity = $r->integer('quantity');
        $item = $catalog->findPublicSku($sku);
        $targetQuantity = ($cart->items()[$sku] ?? 0) + $quantity;
        $testQuantityValid = $item === null
            || ! $item->isIntegrationTest
            || $targetQuantity === NdiSandboxContract::QUANTITY;
        if ($item === null
            || $targetQuantity > 40
            || ! $testQuantityValid
            || $pricing->forItem($item) === null
            || ! $item->stock->canPurchase($targetQuantity)) {
            return back()->with('cart_error', __('commerce.item_not_purchasable'));
        }

        $cart->put($sku, $targetQuantity);

        if ($r->string('intent')->toString() === 'checkout') {
            $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';

            return redirect()->route($prefix.'checkout.create');
        }

        return back();
    }

    public function update(CartLineRequest $r, SessionCart $cart, CatalogReadService $catalog, SellingPriceService $pricing)
    {
        $sku = $r->string('sku')->toString();
        $quantity = $r->integer('quantity');
        $item = $catalog->findPublicSku($sku);
        $testQuantityValid = $item === null
            || ! $item->isIntegrationTest
            || $quantity === NdiSandboxContract::QUANTITY;
        if ($item === null
            || ! $testQuantityValid
            || $pricing->forItem($item) === null
            || ! $item->stock->canPurchase($quantity)) {
            return back()->with('cart_error', __('commerce.item_not_purchasable'));
        }

        $cart->put($sku, $quantity);

        return back();
    }

    public function remove(CartLineRequest $r, SessionCart $cart)
    {
        $cart->remove($r->string('sku')->toString());

        return back();
    }

    public function addServiceAddon(CartServiceAddonRequest $request, SessionCart $cart, CartResolver $resolver)
    {
        $snapshot = $resolver->resolve();
        if ($snapshot->lines !== []
            && collect($snapshot->lines)->every(fn ($line): bool => $line->item->isIntegrationTest)) {
            return back()->with('cart_error', __('commerce.sandbox_services_forbidden'));
        }

        $cart->addServiceAddon($request->string('code')->toString());

        return back();
    }

    public function removeServiceAddon(CartServiceAddonRequest $request, SessionCart $cart)
    {
        $cart->removeServiceAddon($request->string('code')->toString());

        return back();
    }
}
