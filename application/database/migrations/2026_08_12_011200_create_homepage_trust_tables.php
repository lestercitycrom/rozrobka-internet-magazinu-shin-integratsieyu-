<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_rating_summaries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('platform', 24)->unique();
            $table->decimal('rating', 2, 1);
            $table->unsignedInteger('review_count')->nullable();
            $table->string('count_label_nb', 80)->nullable();
            $table->string('count_label_ru', 80)->nullable();
            $table->string('link_label_nb', 160)->nullable();
            $table->string('link_label_ru', 160)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            $table->timestamps();
            $table->index(['publication_status', 'sort_order']);
        });

        Schema::create('homepage_business_partners', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->foreignUlid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('website_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            $table->timestamps();
            $table->index(['publication_status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_business_partners');
        Schema::dropIfExists('homepage_rating_summaries');
    }
};
