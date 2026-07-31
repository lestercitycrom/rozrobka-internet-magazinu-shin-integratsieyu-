<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MediaHeroAssignmentController extends Controller
{
    public function store(Request $r, string $type, int $id)
    {
        $d = $r->validate(['media_asset_id' => ['required', Rule::exists('media_assets', 'id')->where('is_active', true)]]);
        $model = $this->model($type, $id);
        $model->mediaAssignments()->updateOrCreate(['role' => $type === 'product' ? 'primary' : 'hero'], ['media_asset_id' => $d['media_asset_id']]);

        return back();
    }

    public function destroy(string $type, int $id)
    {
        $model = $this->model($type, $id);
        $model->mediaAssignments()->where('role', $type === 'product' ? 'primary' : 'hero')->delete();

        return back();
    }

    private function model(string $type, int $id): mixed
    {
        return match ($type) {
            'service' => ServicePage::findOrFail($id),'content' => ContentPage::findOrFail($id),'product' => Product::findOrFail($id),default => abort(404)
        };
    }
}
