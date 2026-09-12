<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageBusinessPartner;
use App\Modules\Content\Models\MediaAsset;
use App\Support\PublicHttpUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepageBusinessPartnerController
{
    public function index()
    {
        $partners = HomepageBusinessPartner::query()->with('mediaAsset')->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.homepage-business-partners.index', compact('partners'));
    }

    public function create()
    {
        return $this->form(new HomepageBusinessPartner);
    }

    public function store(Request $request): RedirectResponse
    {
        $partner = HomepageBusinessPartner::create($this->validated($request));

        return redirect()->route('admin.homepage-business-partners.edit', $partner)
            ->with('status', __('admin.homepage_partners.saved'));
    }

    public function edit(HomepageBusinessPartner $homepageBusinessPartner)
    {
        return $this->form($homepageBusinessPartner);
    }

    public function update(Request $request, HomepageBusinessPartner $homepageBusinessPartner): RedirectResponse
    {
        $homepageBusinessPartner->update([
            ...$this->validated($request),
            'manifest_hash' => null,
        ]);

        return redirect()->route('admin.homepage-business-partners.edit', $homepageBusinessPartner)
            ->with('status', __('admin.homepage_partners.saved'));
    }

    public function destroy(HomepageBusinessPartner $homepageBusinessPartner): RedirectResponse
    {
        $homepageBusinessPartner->delete();

        return redirect()->route('admin.homepage-business-partners.index')
            ->with('status', __('admin.homepage_partners.deleted'));
    }

    private function form(HomepageBusinessPartner $partner)
    {
        return view('admin.homepage-business-partners.form', [
            'partner' => $partner,
            'assets' => MediaAsset::query()->where('is_active', true)->where('mime', 'like', 'image/%')->orderBy('original_name')->get(),
            'statuses' => ContentPageStatus::cases(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $published = $request->input('publication_status') === ContentPageStatus::Published->value;
        $urlRule = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && ! PublicHttpUrl::isValid($value)) {
                $fail(__('admin.homepage_partners.invalid_url'));
            }
        };

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'media_asset_id' => [
                $published ? 'required' : 'nullable',
                'nullable',
                Rule::exists('media_assets', 'id')->where(
                    static fn ($query) => $query->where('is_active', true)->where('mime', 'like', 'image/%'),
                ),
            ],
            'website_url' => ['nullable', 'string', 'max:2048', $urlRule],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
        ]);
    }
}
