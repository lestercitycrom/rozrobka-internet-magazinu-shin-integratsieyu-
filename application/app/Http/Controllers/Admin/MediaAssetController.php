<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMediaSource;
use App\Modules\Content\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaAssetController extends Controller
{
    public function index()
    {
        $assets = MediaAsset::query()->latest();
        if (! request()->user()->isOwner()) {
            $assets
                ->whereNotIn('id', ProductMediaSource::query()->select('media_asset_id')->whereNotNull('media_asset_id'))
                ->whereDoesntHave('assignments', fn ($query) => $query->where('assignable_type', Product::class));
        }

        return view('admin.media.index', ['assets' => $assets->paginate(30)]);
    }

    public function update(Request $request, MediaAsset $mediaAsset)
    {
        $this->assertContentAsset($request, $mediaAsset);
        $mediaAsset->update($request->validate(['alt_nb' => ['nullable', 'string', 'max:255'], 'alt_ru' => ['nullable', 'string', 'max:255']]));

        return back()->with('status', __('media_ui.updated'));
    }

    public function store(Request $r)
    {
        $r->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm', 'max:25600'], 'alt_nb' => ['nullable', 'string', 'max:255'], 'alt_ru' => ['nullable', 'string', 'max:255']]);
        $f = $r->file('image');
        $mime = $f->getMimeType();
        $imageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $videoMimes = ['video/mp4', 'video/webm'];
        abort_unless(in_array($mime, [...$imageMimes, ...$videoMimes], true), 422);
        [$w, $h] = in_array($mime, $imageMimes, true) ? (getimagesize($f->getRealPath()) ?: [0, 0]) : [0, 0];
        abort_if(in_array($mime, $imageMimes, true) && ($w < 1 || $h < 1), 422);
        $path = 'media/'.Str::random(40).'.'.$f->extension();
        Storage::disk('public')->putFileAs('media', $f, basename($path));
        MediaAsset::create(['disk' => 'public', 'path' => $path, 'original_name' => $f->getClientOriginalName(), 'mime' => $mime, 'byte_size' => $f->getSize(), 'width' => $w, 'height' => $h, 'sha256' => hash_file('sha256', $f->getRealPath()), 'alt_nb' => $r->input('alt_nb'), 'alt_ru' => $r->input('alt_ru'), 'uploader_id' => $r->user()->id]);

        return back()->with('status', __('media_ui.uploaded'));
    }

    public function destroy(Request $request, MediaAsset $mediaAsset)
    {
        $this->assertContentAsset($request, $mediaAsset);
        abort_if($mediaAsset->assignments()->exists(), 409);
        abort_if(ProductMediaSource::query()->where('media_asset_id', $mediaAsset->id)->exists(), 409);
        Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);
        $mediaAsset->delete();

        return back();
    }

    private function assertContentAsset(Request $request, MediaAsset $mediaAsset): void
    {
        if ($request->user()->isOwner()) {
            return;
        }

        abort_if(ProductMediaSource::query()->where('media_asset_id', $mediaAsset->id)->exists(), 403);
        abort_if($mediaAsset->assignments()->where('assignable_type', Product::class)->exists(), 403);
    }
}
