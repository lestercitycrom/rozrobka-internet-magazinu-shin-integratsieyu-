<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepageReviewController
{
    public function index()
    {
        $reviews = HomepageReview::query()->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.homepage-reviews.index', compact('reviews'));
    }

    public function create()
    {
        return view('admin.homepage-reviews.form', ['review' => new HomepageReview, 'statuses' => ContentPageStatus::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $review = HomepageReview::create($this->validated($request));

        return redirect()->route('admin.homepage-reviews.edit', $review)->with('status', __('admin.homepage_reviews.saved'));
    }

    public function edit(HomepageReview $homepageReview)
    {
        return view('admin.homepage-reviews.form', ['review' => $homepageReview, 'statuses' => ContentPageStatus::cases()]);
    }

    public function update(Request $request, HomepageReview $homepageReview): RedirectResponse
    {
        $homepageReview->update($this->validated($request));

        return redirect()->route('admin.homepage-reviews.edit', $homepageReview)->with('status', __('admin.homepage_reviews.saved'));
    }

    public function destroy(HomepageReview $homepageReview): RedirectResponse
    {
        $homepageReview->delete();

        return redirect()->route('admin.homepage-reviews.index')->with('status', __('admin.homepage_reviews.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $published = $request->input('publication_status') === ContentPageStatus::Published->value;

        return $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
            'quote_nb' => ['nullable', 'string', 'max:5000'],
            'author_nb' => ['nullable', 'string', 'max:160'],
            'source_label_nb' => ['nullable', 'string', 'max:120'],
            'quote_ru' => ['nullable', 'string', 'max:5000'],
            'author_ru' => ['nullable', 'string', 'max:160'],
            'source_label_ru' => ['nullable', 'string', 'max:120'],
            'source_url' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ]);
    }
}
