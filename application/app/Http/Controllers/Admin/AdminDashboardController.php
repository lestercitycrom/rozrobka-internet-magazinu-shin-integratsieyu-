<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'orderCounts' => ['total' => Order::query()->count(), 'attention' => Order::query()->whereIn('status', ['pending_review', 'failed'])->count()],
            'serviceRequestCounts' => [
                'total' => ServiceRequest::query()->count(),
                'attention' => ServiceRequest::query()->where('status', 'new')->count(),
            ],
        ]);
    }
}
