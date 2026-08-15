<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_settings')) {
            return;
        }

        if (Schema::hasColumn('storefront_settings', 'logistics_title_nb')) {
            DB::table('storefront_settings')
                ->where(fn ($query) => $query
                    ->whereNull('logistics_title_nb')
                    ->orWhere('logistics_title_nb', 'Hvor passer det best?'))
                ->update(['logistics_title_nb' => 'Dekkhotell']);
        }

        if (Schema::hasColumn('storefront_settings', 'logistics_title_ru')) {
            DB::table('storefront_settings')
                ->where(fn ($query) => $query
                    ->whereNull('logistics_title_ru')
                    ->orWhere('logistics_title_ru', 'Где удобнее?'))
                ->update(['logistics_title_ru' => 'Хранение колёс']);
        }
    }

    public function down(): void
    {
        // Copy written by an administrator after this correction is never rolled back.
    }
};
