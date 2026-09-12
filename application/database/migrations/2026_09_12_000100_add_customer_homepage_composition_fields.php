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

    public function down(): void
    {
        Schema::table('public_service_prices', function (Blueprint $table): void {
            $table->dropColumn(['manifest_hash', 'customer_homepage_composition_manifest']);
        });

        Schema::table('homepage_business_partners', function (Blueprint $table): void {
            $table->dropUnique(['content_key']);
            $table->dropColumn(['content_key', 'manifest_hash', 'deleted_at']);
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropForeign(['hotel_video_media_asset_id']);
            $table->dropColumn([
                'hotel_video_media_asset_id',
                'customer_homepage_composition_manifest',
                'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb', 'contact_map_note_nb',
                'mobile_details_title_ru', 'mobile_details_body_1_ru', 'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru', 'contact_map_note_ru',
            ]);
        });
    }
};
