<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_promotions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            foreach (['nb', 'ru'] as $locale) {
                $table->string("title_{$locale}");
                $table->text("body_{$locale}")->nullable();
                $table->string("cta_label_{$locale}")->nullable();
            }
            $table->string('cta_url', 2048)->nullable();
            $table->timestamps();
            $table->index(['publication_status', 'sort_order']);
        });

        Schema::create('homepage_reviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            foreach (['nb', 'ru'] as $locale) {
                $table->text("quote_{$locale}")->nullable();
                $table->string("author_{$locale}")->nullable();
                $table->string("source_label_{$locale}")->nullable();
            }
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();
            $table->index(['publication_status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_reviews');
        Schema::dropIfExists('homepage_promotions');
    }
};
