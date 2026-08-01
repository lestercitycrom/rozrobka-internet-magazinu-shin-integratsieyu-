<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function index()
    {
        $requests = ServiceRequest::latest()->paginate(25);

        return view('admin.requests.index', [
            'requests' => $requests,
            'serviceTitles' => $this->serviceTitles($requests->pluck('service_slug')->all()),
        ]);
    }

    public function show(ServiceRequest $request)
    {
        return view('admin.requests.show', [
            'request' => $request,
            'serviceTitle' => $this->serviceTitles([$request->service_slug])[$request->service_slug] ?? $request->service_slug,
        ]);
    }

    public function update(Request $input, ServiceRequest $request)
    {
        $data = $input->validate(['status' => ['required', 'in:new,contacted,closed'], 'admin_note' => ['nullable', 'string', 'max:4000']]);
        $request->update($data);

        return back()->with('status', __('service_requests.saved'));
    }

    /** @param list<string> $slugs @return array<string, string> */
    private function serviceTitles(array $slugs): array
    {
        $locale = app()->getLocale();

        return ServicePage::query()
            ->whereIn('slug', array_unique($slugs))
            ->get(['slug', 'title_nb', 'title_ru'])
            ->mapWithKeys(fn (ServicePage $page): array => [
                $page->slug => $locale === 'ru' && filled($page->title_ru) ? $page->title_ru : $page->title_nb,
            ])
            ->all();
    }
}
