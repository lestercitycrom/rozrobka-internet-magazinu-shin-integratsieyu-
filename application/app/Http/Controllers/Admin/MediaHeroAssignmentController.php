<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MediaHeroAssignmentController extends Controller
{
    public function store(Request $r, string $type, int $id)
    {
        abort_if($type === 'product' && ! $r->user()->isOwner(), 403);
        $d = $r->validate(['media_asset_id' => ['required', Rule::exists('media_assets', 'id')->where(
            static fn ($query) => $query->where('is_active', true)->where('mime', 'like', 'image/%'),
        )]]);
        $model = $this->model($type, $id);
        $model->mediaAssignments()->updateOrCreate(['role' => $type === 'product' ? 'primary' : 'hero'], ['media_asset_id' => $d['media_asset_id']]);

        if ($model instanceof Product) {
            ProductMediaSource::query()
                ->where('product_id', $model->id)
                ->where('media_asset_id', $d['media_asset_id'])
                ->update(['is_suppressed' => false]);
        }

        return back();
    }

    public function destroy(Request $request, string $type, int $id)
    {
        abort_if($type === 'product' && ! $request->user()->isOwner(), 403);
        $model = $this->model($type, $id);
        $model->mediaAssignments()->where('role', $type === 'product' ? 'primary' : 'hero')->delete();

        if ($model instanceof Product) {
            ProductMediaSource::query()->where('product_id', $model->id)->update(['is_suppressed' => true]);
        }

        return back();
    }

    private function model(string $type, int $id): mixed
    {
        return match ($type) {
            'service' => ServicePage::findOrFail($id),'content' => ContentPage::findOrFail($id),'product' => Product::findOrFail($id),default => abort(404)
        };
    }
}
