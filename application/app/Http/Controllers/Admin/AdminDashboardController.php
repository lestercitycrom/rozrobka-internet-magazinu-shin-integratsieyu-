<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', ['orderCounts' => ['total' => Order::query()->count(), 'attention' => Order::query()->whereIn('status', ['pending_review', 'failed'])->count()]]);
    }
}
