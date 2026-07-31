<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAssignment extends Model
{
    protected $fillable = ['media_asset_id', 'assignable_type', 'assignable_id', 'role'];

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
