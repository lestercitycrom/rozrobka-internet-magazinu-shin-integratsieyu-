<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index()
    {
        return view('admin.requests.index', ['requests' => ServiceRequest::latest()->paginate(25)]);
    }

    public function show(ServiceRequest $request)
    {
        return view('admin.requests.show', compact('request'));
    }

    public function update(Request $input, ServiceRequest $request)
    {
        $data = $input->validate(['status' => ['required', 'in:new,contacted,closed'], 'admin_note' => ['nullable', 'string', 'max:4000']]);
        $request->update($data);

        return back();
    }
}
