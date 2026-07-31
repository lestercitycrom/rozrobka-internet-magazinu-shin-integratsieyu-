<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('slug', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            foreach (['nb', 'ru'] as $locale) {
                $table->string("title_{$locale}");
                $table->text("excerpt_{$locale}")->nullable();
                $table->longText("body_{$locale}")->nullable();
                $table->string("meta_title_{$locale}")->nullable();
                $table->text("meta_description_{$locale}")->nullable();
            }
            $table->timestamps();
            $table->index(['publication_status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
