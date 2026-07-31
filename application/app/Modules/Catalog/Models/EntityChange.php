<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'import_run_id',
    'entity_type',
    'entity_id',
    'operation',
    'before_json',
    'after_json',
    'field_diff_json',
    'source_artifact_id',
    'occurred_at',
])]
class EntityChange extends Model
{
    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'field_diff_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
