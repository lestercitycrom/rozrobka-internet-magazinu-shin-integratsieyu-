<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepagePromotionController
{
    public function index()
    {
        $promotions = HomepagePromotion::query()->with('mediaAsset')->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.homepage-promotions.index', compact('promotions'));
    }

    public function create()
    {
        return $this->form(new HomepagePromotion);
    }

    public function store(Request $request): RedirectResponse
    {
        $promotion = HomepagePromotion::create($this->validated($request));

        return redirect()->route('admin.homepage-promotions.edit', $promotion)->with('status', __('admin.homepage_promotions.saved'));
    }

    public function edit(HomepagePromotion $homepagePromotion)
    {
        return $this->form($homepagePromotion);
    }

    public function update(Request $request, HomepagePromotion $homepagePromotion): RedirectResponse
    {
        $homepagePromotion->update($this->validated($request));

        return redirect()->route('admin.homepage-promotions.edit', $homepagePromotion)->with('status', __('admin.homepage_promotions.saved'));
    }

    public function destroy(HomepagePromotion $homepagePromotion): RedirectResponse
    {
        $homepagePromotion->delete();

        return redirect()->route('admin.homepage-promotions.index')->with('status', __('admin.homepage_promotions.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
            'title_nb' => ['required', 'string', 'max:255'],
            'body_nb' => ['nullable', 'string', 'max:5000'],
            'cta_label_nb' => ['nullable', 'string', 'max:120'],
            'title_ru' => ['required', 'string', 'max:255'],
            'body_ru' => ['nullable', 'string', 'max:5000'],
            'cta_label_ru' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }

                $url = trim((string) $value);
                $validRelativeUrl = str_starts_with($url, '/') && ! str_starts_with($url, '//');
                $scheme = parse_url($url, PHP_URL_SCHEME);
                $validExternalUrl = in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
                if (! $validRelativeUrl && ! $validExternalUrl) {
                    $fail(__('admin.homepage_promotions.invalid_url'));
                }
            }],
            'media_asset_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $isActiveImage = MediaAsset::query()
                    ->whereKey($value)
                    ->where('is_active', true)
                    ->where('mime', 'like', 'image/%')
                    ->exists();

                if (! $isActiveImage) {
                    $fail(__('admin.homepage_promotions.invalid_media'));
                }
            }],
        ]);
    }

    private function form(HomepagePromotion $promotion)
    {
        return view('admin.homepage-promotions.form', [
            'promotion' => $promotion,
            'statuses' => ContentPageStatus::cases(),
            'assets' => MediaAsset::query()
                ->where('is_active', true)
                ->where('mime', 'like', 'image/%')
                ->orderBy('original_name')
                ->get(),
        ]);
    }
}
