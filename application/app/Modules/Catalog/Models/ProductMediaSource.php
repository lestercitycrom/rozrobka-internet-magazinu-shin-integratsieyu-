<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Content\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMediaSource extends Model
{
    protected $fillable = [
        'product_id', 'source_kind', 'source_url', 'source_artifact_id', 'media_asset_id', 'status',
        'attempt_count', 'last_error', 'last_attempted_at', 'downloaded_at',
    ];

    protected function casts(): array
    {
        return ['last_attempted_at' => 'datetime', 'downloaded_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
