<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateServiceRequestQuickAction;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestQuickAction;
use App\Modules\ServiceBooking\ServiceRequestReturnContext;
use Illuminate\Http\RedirectResponse;

final class ServiceRequestQuickActionController extends Controller
{
    public function __invoke(
        UpdateServiceRequestQuickAction $request,
        ServiceRequest $serviceRequest,
        ServiceRequestQuickAction $action,
        ServiceRequestReturnContext $contexts,
    ): RedirectResponse {
        $updated = $action->execute($serviceRequest, $request->user(), $request->validated());
        $returnTo = $contexts->fromRequest($request);

        return redirect($returnTo ?? route('admin.service-requests.show', $updated))
            ->with('status', __('service_requests.quick_saved'));
    }
}
