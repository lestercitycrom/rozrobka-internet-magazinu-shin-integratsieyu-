<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media_sources', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->string('source_kind', 32)->default('ndi_catalog');
            $table->text('source_url');
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->foreignUlid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media_sources');
    }
};
