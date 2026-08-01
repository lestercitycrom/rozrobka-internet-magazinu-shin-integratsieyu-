<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('uploader_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Supplier-managed rows cannot satisfy the restored NOT NULL
        // constraint. Deleting them also removes their assignments through
        // the existing foreign keys; the previous migration still exists at
        // this point and nulls its provenance references.
        $supplierAssets = DB::table('media_assets')->whereNull('uploader_id')->get(['disk', 'path']);

        foreach ($supplierAssets->groupBy('disk') as $disk => $assets) {
            Storage::disk((string) $disk)->delete($assets->pluck('path')->all());
        }

        DB::table('media_assets')->whereNull('uploader_id')->delete();

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('uploader_id')->nullable(false)->change();
        });
    }
};
