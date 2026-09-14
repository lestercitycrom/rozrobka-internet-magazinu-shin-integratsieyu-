<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Orders\Models\Order;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $canReadRequests = $user->isOwner() || $user->role === UserRole::Operations;

        return view('admin.dashboard', [
            'orderCounts' => $user->isOwner() ? [
                'total' => Order::query()->count(),
                'attention' => Order::query()->whereIn('status', ['pending_review', 'failed'])->count(),
            ] : null,
            'serviceRequestCounts' => $canReadRequests ? [
                'total' => ServiceRequest::query()->count(),
                'attention' => ServiceRequest::query()->where('status', 'new')->count(),
            ] : null,
        ]);
    }
}
