<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateProductPublicationRequest;
use App\Modules\Catalog\Enums\PublicationStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Publication\EligibleCatalogPublication;
use App\Modules\Content\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $mediaAssets = MediaAsset::query()->where('is_active', true)->latest()->get();

        return view('admin.catalog.show', [
            'product' => $product,
            'mediaAssets' => $mediaAssets,
            'publicationStatuses' => PublicationStatus::cases(),
        ]);
    }

    public function updatePublication(UpdateProductPublicationRequest $request, Product $product): RedirectResponse
    {
        $product->update(['publication_status' => $request->validated('publication_status')]);

        return redirect()->route('admin.catalog.show', $product)->with('status', __('catalog.publication_saved'));
    }

    public function publicationPreview(EligibleCatalogPublication $publication)
    {
        return view('admin.catalog.publication-preview', ['candidates' => $publication->preview()]);
    }

    public function publishEligible(EligibleCatalogPublication $publication): RedirectResponse
    {
        $count = $publication->publish();

        return redirect()->route('admin.catalog.index')->with('status', trans_choice('catalog.bulk_published', $count, ['count' => $count]));
    }
}
