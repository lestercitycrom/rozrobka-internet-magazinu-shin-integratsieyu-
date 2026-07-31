<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\ContentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ContentPageController
{
    public function index()
    {
        $pages = ContentPage::query()->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.content-pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.content-pages.form', ['page' => new ContentPage, 'statuses' => ContentPageStatus::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = ContentPage::create($this->validated($request));

        return redirect()->route('admin.content-pages.edit', $page)->with('status', __('admin.content.saved'));
    }

    public function edit(ContentPage $contentPage)
    {
        return view('admin.content-pages.form', ['page' => $contentPage, 'statuses' => ContentPageStatus::cases()]);
    }

    public function update(Request $request, ContentPage $contentPage): RedirectResponse
    {
        $contentPage->update($this->validated($request, $contentPage));

        return redirect()->route('admin.content-pages.edit', $contentPage)->with('status', __('admin.content.saved'));
    }

    public function destroy(ContentPage $contentPage): RedirectResponse
    {
        $contentPage->delete();

        return redirect()->route('admin.content-pages.index')->with('status', __('admin.content.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ContentPage $page = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('content_pages', 'slug')->ignore($page)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
            'title_nb' => ['required', 'string', 'max:255'], 'excerpt_nb' => ['nullable', 'string'], 'body_nb' => ['nullable', 'string'], 'meta_title_nb' => ['nullable', 'string', 'max:255'], 'meta_description_nb' => ['nullable', 'string'],
            'title_ru' => ['required', 'string', 'max:255'], 'excerpt_ru' => ['nullable', 'string'], 'body_ru' => ['nullable', 'string'], 'meta_title_ru' => ['nullable', 'string', 'max:255'], 'meta_description_ru' => ['nullable', 'string'],
        ]);
    }
}
