<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateProductPublicationRequest;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Publication\EligibleCatalogPublication;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\MediaAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CatalogController
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'type' => ['nullable', 'in:tire,wheel'], 'status' => ['nullable', 'string', 'max:32']]);
        $products = Product::query()->with('brand')->withCount('supplierSkus')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($nested) => $nested->where('title', 'like', "%{$q}%")->orWhere('model_name', 'like', "%{$q}%")))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('product_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('publication_status', $status))
            ->orderByDesc('last_catalog_seen_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.catalog.index', compact('products', 'filters'));
    }

    public function show(Product $product)
    {
        $product->load([
            'brand',
            'supplierSkus.supplier',
            'supplierSkus.prices.priceList',
            'supplierSkus.currentStock.warehouse',
            'tireSpec',
            'wheelSpec',
            'mediaAssignments.asset',
        ]);

        $mediaAssets = MediaAsset::query()->where('is_active', true)->where('mime', 'like', 'image/%')->latest()->get();
        $mediaContexts = MediaAssignment::query()
            ->whereIn('media_asset_id', $mediaAssets->modelKeys())
            ->where('assignable_type', Product::class)
            ->select('media_asset_id')
            ->selectRaw('MIN(assignable_id) as representative_product_id')
            ->selectRaw('COUNT(DISTINCT assignable_id) as product_count')
            ->groupBy('media_asset_id')
            ->get()
            ->keyBy('media_asset_id');
        $representativeProducts = Product::query()
            ->whereKey($mediaContexts->pluck('representative_product_id')->filter())
            ->with(['brand', 'supplierSkus' => fn ($query) => $query->orderBy('id')])
            ->get()
            ->keyBy('id');
        $mediaChoices = $mediaAssets->map(function (MediaAsset $asset) use ($mediaContexts, $representativeProducts): array {
            $context = $mediaContexts->get($asset->getKey());
            $representative = $context === null
                ? null
                : $representativeProducts->get((int) $context->representative_product_id);

            return [
                'asset' => $asset,
                'label' => $this->mediaChoiceLabel($asset, $representative, (int) ($context?->product_count ?? 0)),
            ];
        });

        return view('admin.catalog.show', [
            'product' => $product,
            'mediaChoices' => $mediaChoices,
            'publicationStatuses' => PublicationStatus::cases(),
        ]);
    }

    private function mediaChoiceLabel(MediaAsset $asset, ?Product $representative, int $productCount): string
    {
        $parts = [__('media.short_id').' '.Str::upper(Str::substr((string) $asset->getKey(), -8))];

        if ($representative !== null) {
            $parts[] = $representative->brand?->canonical_name;
            $parts[] = $representative->model_name;

            if ($sku = $representative->supplierSkus->first()?->external_sku) {
                $parts[] = 'SKU '.$sku;
            }

            if ($productCount > 1) {
                $parts[] = __('media.linked_products', ['count' => $productCount]);
            }
        } else {
            $parts[] = Str::limit($asset->original_name, 48);
        }

        return implode(' · ', array_values(array_unique(array_filter($parts))));
    }

    public function updatePublication(UpdateProductPublicationRequest $request, Product $product): RedirectResponse
    {
        $product->update(['publication_status' => $request->validated('publication_status')]);

        return redirect()->route('admin.catalog.show', $product)->with('status', __('catalog.publication_saved'));
    }

    public function publicationPreview(EligibleCatalogPublication $publication)
    {
        $candidates = $publication->preview();

        return view('admin.catalog.publication-preview', [
            'candidates' => $candidates,
            'publicationSnapshot' => $publication->snapshot($candidates),
        ]);
    }

    public function publishEligible(Request $request, EligibleCatalogPublication $publication): RedirectResponse
    {
        $validated = $request->validate([
            'publication_snapshot' => ['required', 'string', 'size:64'],
        ]);
        $count = $publication->publish($validated['publication_snapshot']);

        return redirect()->route('admin.catalog.index')->with('status', trans_choice('catalog.bulk_published', $count, ['count' => $count]));
    }
}
