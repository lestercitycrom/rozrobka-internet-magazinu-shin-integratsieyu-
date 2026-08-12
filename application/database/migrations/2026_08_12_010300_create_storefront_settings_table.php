<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table): void {
            $table->id();
            foreach (['nb', 'ru'] as $locale) {
                $table->string("hero_kicker_{$locale}")->nullable();
                $table->string("hero_title_{$locale}")->nullable();
                $table->text("hero_text_{$locale}")->nullable();
                $table->string("hero_primary_label_{$locale}")->nullable();
                $table->string("hero_secondary_label_{$locale}")->nullable();
                $table->string("store_heading_{$locale}")->nullable();
                $table->text("store_lead_{$locale}")->nullable();
                foreach ([1, 2, 3] as $step) {
                    $table->string("store_benefit_{$step}_title_{$locale}")->nullable();
                    $table->text("store_benefit_{$step}_text_{$locale}")->nullable();
                }
            }
            $table->string('hero_primary_url', 2048)->nullable();
            $table->string('hero_secondary_url', 2048)->nullable();
            $table->foreignUlid('hero_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('phone_display', 80)->nullable();
            $table->string('phone_href', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('business_address')->nullable();
            $table->string('map_pin_address')->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->string('map_embed_url', 2048)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
