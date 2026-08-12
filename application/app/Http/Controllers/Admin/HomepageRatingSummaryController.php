<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ReviewPlatform;
use App\Modules\Content\Models\HomepageRatingSummary;
use App\Support\PublicHttpUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepageRatingSummaryController
{
    public function index()
    {
        $summaries = HomepageRatingSummary::query()->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.homepage-rating-summaries.index', compact('summaries'));
    }

    public function create()
    {
        return view('admin.homepage-rating-summaries.form', [
            'summary' => new HomepageRatingSummary,
            'platforms' => ReviewPlatform::cases(),
            'statuses' => ContentPageStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $summary = HomepageRatingSummary::create($this->validated($request));

        return redirect()->route('admin.homepage-rating-summaries.edit', $summary)
            ->with('status', __('admin.homepage_ratings.saved'));
    }

    public function edit(HomepageRatingSummary $homepageRatingSummary)
    {
        return view('admin.homepage-rating-summaries.form', [
            'summary' => $homepageRatingSummary,
            'platforms' => ReviewPlatform::cases(),
            'statuses' => ContentPageStatus::cases(),
        ]);
    }

    public function update(Request $request, HomepageRatingSummary $homepageRatingSummary): RedirectResponse
    {
        $homepageRatingSummary->update($this->validated($request, $homepageRatingSummary));

        return redirect()->route('admin.homepage-rating-summaries.edit', $homepageRatingSummary)
            ->with('status', __('admin.homepage_ratings.saved'));
    }

    public function destroy(HomepageRatingSummary $homepageRatingSummary): RedirectResponse
    {
        $homepageRatingSummary->delete();

        return redirect()->route('admin.homepage-rating-summaries.index')
            ->with('status', __('admin.homepage_ratings.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?HomepageRatingSummary $summary = null): array
    {
        $published = $request->input('publication_status') === ContentPageStatus::Published->value;
        $urlRule = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && ! PublicHttpUrl::isValid($value)) {
                $fail(__('admin.homepage_ratings.invalid_url'));
            }
        };

        return $request->validate([
            'platform' => [
                'required',
                Rule::enum(ReviewPlatform::class),
                Rule::unique('homepage_rating_summaries', 'platform')->ignore($summary),
            ],
            'rating' => ['required', 'numeric', 'between:1,5', 'decimal:0,1'],
            'review_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'count_label_nb' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:80'],
            'count_label_ru' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:80'],
            'link_label_nb' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:160'],
            'link_label_ru' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:160'],
            'source_url' => [$published ? 'required' : 'nullable', 'nullable', 'string', 'max:2048', $urlRule],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
        ]);
    }
}
