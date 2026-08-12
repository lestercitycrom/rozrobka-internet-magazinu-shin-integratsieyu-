<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->foreignUlid('home_store_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignUlid('story_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignUlid('hotel_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignUlid('mobile_media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            foreach (['nb', 'ru'] as $locale) {
                foreach (['chooser_kicker', 'chooser_title', 'chooser_mobile_title', 'chooser_mobile_text', 'chooser_workshop_title', 'chooser_workshop_text', 'chooser_store_kicker', 'chooser_store_title', 'trust_1', 'trust_2', 'trust_3', 'process_kicker', 'process_title', 'process_lead', 'process_1_title', 'process_1_text', 'process_2_title', 'process_2_text', 'process_3_title', 'process_3_text', 'process_4_title', 'process_4_text', 'story_kicker', 'story_title', 'story_text', 'story_quote', 'business_kicker', 'business_title', 'business_text', 'business_proof', 'logistics_kicker', 'logistics_title', 'hotel_kicker', 'hotel_title', 'hotel_text', 'hotel_cta', 'mobile_kicker', 'mobile_title', 'mobile_text', 'mobile_cta', 'contact_kicker', 'contact_title', 'contact_text'] as $field) {
                    str_ends_with($field, '_text') || str_ends_with($field, '_lead') || str_ends_with($field, '_quote')
                        ? $table->text("{$field}_{$locale}")->nullable()
                        : $table->string("{$field}_{$locale}")->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropForeign(['home_store_media_asset_id']);
            $table->dropForeign(['story_media_asset_id']);
            $table->dropForeign(['hotel_media_asset_id']);
            $table->dropForeign(['mobile_media_asset_id']);
            $columns = ['home_store_media_asset_id', 'story_media_asset_id', 'hotel_media_asset_id', 'mobile_media_asset_id'];
            foreach (['nb', 'ru'] as $locale) {
                foreach (['chooser_kicker', 'chooser_title', 'chooser_mobile_title', 'chooser_mobile_text', 'chooser_workshop_title', 'chooser_workshop_text', 'chooser_store_kicker', 'chooser_store_title', 'trust_1', 'trust_2', 'trust_3', 'process_kicker', 'process_title', 'process_lead', 'process_1_title', 'process_1_text', 'process_2_title', 'process_2_text', 'process_3_title', 'process_3_text', 'process_4_title', 'process_4_text', 'story_kicker', 'story_title', 'story_text', 'story_quote', 'business_kicker', 'business_title', 'business_text', 'business_proof', 'logistics_kicker', 'logistics_title', 'hotel_kicker', 'hotel_title', 'hotel_text', 'hotel_cta', 'mobile_kicker', 'mobile_title', 'mobile_text', 'mobile_cta', 'contact_kicker', 'contact_title', 'contact_text'] as $field) {
                    $columns[] = "{$field}_{$locale}";
                }
            }
            $table->dropColumn($columns);
        });
    }
};
