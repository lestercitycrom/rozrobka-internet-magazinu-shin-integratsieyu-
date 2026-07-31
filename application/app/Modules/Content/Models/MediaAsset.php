<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaAsset extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['disk', 'path', 'original_name', 'mime', 'byte_size', 'width', 'height', 'sha256', 'alt_nb', 'alt_ru', 'uploader_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MediaAssignment::class);
    }
}
