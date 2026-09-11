<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\ServicePage;
use App\Support\PublicEditorialUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HomepageServiceCardController
{
    public function index(string $collection)
    {
        $collection = $this->collection($collection);
        $cards = HomepageServiceCard::query()
            ->where('collection', $collection->value)
            ->with(['servicePage', 'mediaAsset'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(30);

        return view('admin.homepage-service-cards.index', compact('cards', 'collection'));
    }

    public function create(string $collection)
    {
        return $this->form($this->collection($collection), new HomepageServiceCard);
    }

    public function store(Request $request, string $collection): RedirectResponse
    {
        $collection = $this->collection($collection);
        $card = HomepageServiceCard::query()->create([
            ...$this->validated($request),
            'collection' => $collection,
            'manifest_hash' => null,
        ]);

        return redirect()->route('admin.homepage-service-collections.edit', [$collection->value, $card])
            ->with('status', __('admin.homepage_service_cards.saved'));
    }

    public function edit(string $collection, HomepageServiceCard $homepageServiceCard)
    {
        $collection = $this->collection($collection);
        $this->belongsTo($homepageServiceCard, $collection);

        return $this->form($collection, $homepageServiceCard);
    }

    public function update(Request $request, string $collection, HomepageServiceCard $homepageServiceCard): RedirectResponse
    {
        $collection = $this->collection($collection);
        $this->belongsTo($homepageServiceCard, $collection);
        $homepageServiceCard->update([...$this->validated($request), 'manifest_hash' => null]);

        return redirect()->route('admin.homepage-service-collections.edit', [$collection->value, $homepageServiceCard])
            ->with('status', __('admin.homepage_service_cards.saved'));
    }

    public function destroy(string $collection, HomepageServiceCard $homepageServiceCard): RedirectResponse
    {
        $collection = $this->collection($collection);
        $this->belongsTo($homepageServiceCard, $collection);
        $homepageServiceCard->delete();

        return redirect()->route('admin.homepage-service-collections.index', $collection->value)
            ->with('status', __('admin.homepage_service_cards.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $published = $request->input('publication_status') === ContentPageStatus::Published->value;
        $requiresCustomUrl = $published && blank($request->input('service_page_id'));
        $validated = $request->validate([
            'service_page_id' => [
                'nullable',
                Rule::exists('service_pages', 'id')->where('publication_status', ServicePagePublicationStatus::Published->value),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'publication_status' => ['required', Rule::enum(ContentPageStatus::class)],
            'title_nb' => ['required', 'string', 'max:255'],
            'description_nb' => [Rule::requiredIf($published), 'nullable', 'string', 'max:5000'],
            'cta_label_nb' => [Rule::requiredIf($published), 'nullable', 'string', 'max:120'],
            'title_ru' => ['required', 'string', 'max:255'],
            'description_ru' => [Rule::requiredIf($published), 'nullable', 'string', 'max:5000'],
            'cta_label_ru' => [Rule::requiredIf($published), 'nullable', 'string', 'max:120'],
            'cta_url' => [Rule::requiredIf($requiresCustomUrl), 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! PublicEditorialUrl::isValid($value)) {
                    $fail(__('admin.homepage_service_cards.invalid_url'));
                }
            }],
            'media_asset_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $valid = MediaAsset::query()
                    ->whereKey($value)
                    ->where('is_active', true)
                    ->where('mime', 'like', 'image/%')
                    ->exists();
                if (! $valid) {
                    $fail(__('admin.homepage_service_cards.invalid_media'));
                }
            }],
        ]);

        if (filled($validated['cta_url'] ?? null)) {
            $validated['cta_url'] = trim($validated['cta_url']);
        }

        return $validated;
    }

    private function form(HomepageServiceCollection $collection, HomepageServiceCard $card)
    {
        return view('admin.homepage-service-cards.form', [
            'collection' => $collection,
            'card' => $card,
            'statuses' => ContentPageStatus::cases(),
            'services' => ServicePage::query()
                ->where('publication_status', ServicePagePublicationStatus::Published->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'assets' => MediaAsset::query()
                ->where('is_active', true)
                ->where('mime', 'like', 'image/%')
                ->orderBy('original_name')
                ->get(),
        ]);
    }

    private function collection(string $collection): HomepageServiceCollection
    {
        return HomepageServiceCollection::tryFrom($collection) ?? abort(404);
    }

    private function belongsTo(HomepageServiceCard $card, HomepageServiceCollection $collection): void
    {
        abort_unless($card->collection === $collection, 404);
    }
}
