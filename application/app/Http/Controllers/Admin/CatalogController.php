<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Catalog\Models\Product;
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
        $product->load(['brand', 'supplierSkus.supplier', 'tireSpec', 'wheelSpec']);

        return view('admin.catalog.show', compact('product'));
    }
}
