<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->foreignUlid('hotel_video_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('customer_homepage_composition_manifest')->nullable();

            foreach (['nb', 'ru'] as $locale) {
                foreach (['mobile_details_title', 'mobile_details_body_1', 'mobile_details_body_2', 'process_cta', 'business_cta', 'business_partners_heading', 'contact_map_note'] as $field) {
                    $table->text("{$field}_{$locale}")->nullable();
                }
            }
        });

        Schema::table('homepage_business_partners', function (Blueprint $table): void {
            $table->string('content_key', 160)->nullable()->unique();
            $table->char('manifest_hash', 64)->nullable();
            $table->softDeletes();
        });

        Schema::table('public_service_prices', function (Blueprint $table): void {
            $table->char('manifest_hash', 64)->nullable();
            $table->json('customer_homepage_composition_manifest')->nullable();
        });
    }
};
