<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_service_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('category_code', 80);
            $table->string('category_title_nb');
            $table->string('category_title_ru');
            $table->string('title_nb');
            $table->string('title_ru');
            $table->text('description_nb')->nullable();
            $table->text('description_ru')->nullable();
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3)->default('NOK');
            $table->boolean('is_active')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->index(['category_code', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_service_prices');
    }
};
