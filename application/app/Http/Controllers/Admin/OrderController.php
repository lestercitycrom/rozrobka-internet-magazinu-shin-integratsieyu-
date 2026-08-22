<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Orders\AdminOrderFilters;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\TransitionOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OrderController
{
    public function index(Request $request)
    {
        try {
            $f = AdminOrderFilters::from($request->query());
        } catch (\InvalidArgumentException) {
            abort(422);
        }

        $orders = Order::query()->withSum('items as product_total_minor', 'line_total_minor')->withSum('serviceItems as service_total_minor', 'line_total_minor')->when($f->status, fn ($q, $v) => $q->where('status', $v->value))->when($f->payment, fn ($q, $v) => $q->where('payment_status', $v->value))->when($f->ndi, fn ($q, $v) => $q->where('ndi_status', $v->value))->when($f->query, fn ($q, $v) => $q->where(fn ($x) => $x->where('public_reference', 'like', "%$v%")->orWhere('customer_email', 'like', "%$v%")))->when($f->from, fn ($q, $v) => $q->whereDate('submitted_at', '>=', $v))->when($f->to, fn ($q, $v) => $q->whereDate('submitted_at', '<=', $v))->latest('submitted_at')->paginate(30)->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'f' => $request->query()]);
    }

    public function show(Order $order, TransitionOrder $t)
    {
        $order->load(['items', 'serviceItems', 'statusHistory.actor', 'ndiOrderAttempt', 'paymentAttempt']);

        return view('admin.orders.show', ['order' => $order, 'allowed' => $t->allowedFor($order)]);
    }

    public function transition(Request $r, Order $order, TransitionOrder $t)
    {
        $d = $r->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'note' => ['nullable', 'string', 'max:1000']]);
        $to = OrderStatus::from($d['status']);
        if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Failed], true) && blank($d['note'] ?? null)) {
            return back()->withErrors(['note' => __('orders.note_required')]);
        }

        $t->execute($order, $to, $r->user(), $d['note'] ?? null);

        return back()->with('status', __('orders.saved'));
    }
}
