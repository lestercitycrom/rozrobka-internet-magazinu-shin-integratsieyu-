<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ServicePageController
{
    public function index()
    {
        $pages = ServicePage::query()->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.services.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.services.form', [
            'page' => new ServicePage,
            'statuses' => ServicePagePublicationStatus::cases(),
            'requestFlows' => ServiceRequestFlow::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $page = ServicePage::create($this->validated($request));

        return redirect()->route('admin.services.edit', $page)->with('status', __('admin.services.saved'));
    }

    public function edit(ServicePage $service)
    {
        return view('admin.services.form', [
            'page' => $service,
            'statuses' => ServicePagePublicationStatus::cases(),
            'requestFlows' => ServiceRequestFlow::cases(),
        ]);
    }

    public function update(Request $request, ServicePage $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('admin.services.edit', $service)->with('status', __('admin.services.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServicePage $page = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('service_pages', 'slug')->ignore($page)],
            'canonical_path' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('service_pages', 'canonical_path')->ignore($page)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ServicePagePublicationStatus::class)],
            'request_flow' => ['required', Rule::enum(ServiceRequestFlow::class)],
            'title_nb' => ['required', 'string', 'max:255'], 'excerpt_nb' => ['nullable', 'string'], 'body_nb' => ['nullable', 'string'], 'seo_title_nb' => ['nullable', 'string', 'max:255'], 'seo_description_nb' => ['nullable', 'string'],
            'title_ru' => ['required', 'string', 'max:255'], 'excerpt_ru' => ['nullable', 'string'], 'body_ru' => ['nullable', 'string'], 'seo_title_ru' => ['nullable', 'string', 'max:255'], 'seo_description_ru' => ['nullable', 'string'],
        ]);
    }
}
