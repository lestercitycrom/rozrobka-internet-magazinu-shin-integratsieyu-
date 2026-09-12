<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deliberately small, typed CMS surface for the few recurring storefront
 * blocks that are not already managed as pages, services, promotions or reviews.
 */
#[Fillable([
    'hero_kicker_nb', 'hero_title_nb', 'hero_text_nb', 'hero_primary_label_nb', 'hero_secondary_label_nb',
    'hero_kicker_ru', 'hero_title_ru', 'hero_text_ru', 'hero_primary_label_ru', 'hero_secondary_label_ru',
    'hero_primary_url', 'hero_secondary_url', 'hero_media_asset_id',
    'phone_display', 'phone_href', 'email', 'business_address', 'map_pin_address', 'map_url', 'map_embed_url',
    'instagram_url', 'snapchat_url', 'facebook_url',
    'store_heading_nb', 'store_lead_nb', 'store_heading_ru', 'store_lead_ru',
    'store_benefit_1_title_nb', 'store_benefit_1_text_nb', 'store_benefit_2_title_nb', 'store_benefit_2_text_nb', 'store_benefit_3_title_nb', 'store_benefit_3_text_nb',
    'store_benefit_1_title_ru', 'store_benefit_1_text_ru', 'store_benefit_2_title_ru', 'store_benefit_2_text_ru', 'store_benefit_3_title_ru', 'store_benefit_3_text_ru',
    'home_store_media_asset_id', 'story_media_asset_id', 'hotel_media_asset_id', 'mobile_media_asset_id', 'hotel_video_media_asset_id',
    'chooser_kicker_nb', 'chooser_title_nb', 'chooser_mobile_title_nb', 'chooser_mobile_text_nb', 'chooser_workshop_title_nb', 'chooser_workshop_text_nb', 'chooser_store_kicker_nb', 'chooser_store_title_nb',
    'trust_1_nb', 'trust_2_nb', 'trust_3_nb', 'process_kicker_nb', 'process_title_nb', 'process_lead_nb', 'process_1_title_nb', 'process_1_text_nb', 'process_2_title_nb', 'process_2_text_nb', 'process_3_title_nb', 'process_3_text_nb', 'process_4_title_nb', 'process_4_text_nb',
    'story_kicker_nb', 'story_title_nb', 'story_text_nb', 'story_quote_nb', 'business_kicker_nb', 'business_title_nb', 'business_text_nb', 'business_proof_nb',
    'logistics_kicker_nb', 'logistics_title_nb', 'hotel_kicker_nb', 'hotel_title_nb', 'hotel_text_nb', 'hotel_cta_nb', 'mobile_kicker_nb', 'mobile_title_nb', 'mobile_text_nb', 'mobile_cta_nb',
    'contact_kicker_nb', 'contact_title_nb', 'contact_text_nb', 'contact_map_note_nb', 'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb',
    'chooser_kicker_ru', 'chooser_title_ru', 'chooser_mobile_title_ru', 'chooser_mobile_text_ru', 'chooser_workshop_title_ru', 'chooser_workshop_text_ru', 'chooser_store_kicker_ru', 'chooser_store_title_ru',
    'trust_1_ru', 'trust_2_ru', 'trust_3_ru', 'process_kicker_ru', 'process_title_ru', 'process_lead_ru', 'process_1_title_ru', 'process_1_text_ru', 'process_2_title_ru', 'process_2_text_ru', 'process_3_title_ru', 'process_3_text_ru', 'process_4_title_ru', 'process_4_text_ru',
    'story_kicker_ru', 'story_title_ru', 'story_text_ru', 'story_quote_ru', 'business_kicker_ru', 'business_title_ru', 'business_text_ru', 'business_proof_ru',
    'logistics_kicker_ru', 'logistics_title_ru', 'hotel_kicker_ru', 'hotel_title_ru', 'hotel_text_ru', 'hotel_cta_ru', 'mobile_kicker_ru', 'mobile_title_ru', 'mobile_text_ru', 'mobile_cta_ru',
    'contact_kicker_ru', 'contact_title_ru', 'contact_text_ru', 'contact_map_note_ru', 'mobile_details_title_ru', 'mobile_details_body_1_ru', 'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru',
])]
final class StorefrontSettings extends Model
{
    protected function casts(): array
    {
        return [
            'customer_homepage_composition_manifest' => 'array',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_media_asset_id')->where('is_active', true);
    }

    public function homeStoreMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'home_store_media_asset_id')->where('is_active', true);
    }

    public function storyMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'story_media_asset_id')->where('is_active', true);
    }

    public function hotelMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hotel_media_asset_id')->where('is_active', true);
    }

    public function mobileMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mobile_media_asset_id')->where('is_active', true);
    }

    public function hotelVideoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hotel_video_media_asset_id')
            ->where('is_active', true)
            ->whereIn('mime', ['video/mp4', 'video/webm']);
    }
}
