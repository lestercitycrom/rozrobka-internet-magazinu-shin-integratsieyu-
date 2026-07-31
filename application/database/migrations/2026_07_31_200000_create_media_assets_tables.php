<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('disk')->default('public');
            $t->string('path')->unique();
            $t->string('original_name');
            $t->string('mime', 32);
            $t->unsignedBigInteger('byte_size');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->char('sha256', 64);
            $t->string('alt_nb')->nullable();
            $t->string('alt_ru')->nullable();
            $t->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('media_assignments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignUlid('media_asset_id')->constrained()->cascadeOnDelete();
            $t->morphs('assignable');
            $t->string('role', 32);
            $t->timestamps();
            $t->unique(['assignable_type', 'assignable_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assignments');
        Schema::dropIfExists('media_assets');
    }
};
