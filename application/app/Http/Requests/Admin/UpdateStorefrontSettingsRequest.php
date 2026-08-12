<?php

namespace App\Http\Requests\Admin;

use App\Support\GoogleMapsEmbedUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStorefrontSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $localizedShort = ['nullable', 'string', 'max:255'];
        $localizedLong = ['nullable', 'string', 'max:2000'];

        return [
            'hero_kicker_nb' => $localizedShort, 'hero_title_nb' => $localizedShort, 'hero_text_nb' => $localizedLong,
            'hero_primary_label_nb' => $localizedShort, 'hero_secondary_label_nb' => $localizedShort,
            'hero_kicker_ru' => $localizedShort, 'hero_title_ru' => $localizedShort, 'hero_text_ru' => $localizedLong,
            'hero_primary_label_ru' => $localizedShort, 'hero_secondary_label_ru' => $localizedShort,
            'hero_primary_url' => ['nullable', 'string', 'max:2048', $this->safeLink()],
            'hero_secondary_url' => ['nullable', 'string', 'max:2048', $this->safeLink()],
            'hero_media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')->where('is_active', true)],
            'home_store_media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')->where('is_active', true)],
            'story_media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')->where('is_active', true)],
            'hotel_media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')->where('is_active', true)],
            'mobile_media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')->where('is_active', true)],
            'phone_display' => ['nullable', 'string', 'max:80'],
            'phone_href' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ()-]*$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'map_pin_address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:2048'],
            'map_embed_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && GoogleMapsEmbedUrl::sanitize($value) === null) {
                    $fail(__('storefront_settings.invalid_map_embed'));
                }
            }],
            ...$this->storeCopyRules('nb', $localizedShort, $localizedLong),
            ...$this->storeCopyRules('ru', $localizedShort, $localizedLong),
            ...$this->homeContentRules('nb', $localizedShort, $localizedLong),
            ...$this->homeContentRules('ru', $localizedShort, $localizedLong),
        ];
    }

    private function safeLink(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $value = trim((string) $value);
            $isInternal = str_starts_with($value, '/') && ! str_starts_with($value, '//');
            $isExternal = filter_var($value, FILTER_VALIDATE_URL) && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);

            if (! $isInternal && ! $isExternal) {
                $fail(__('storefront_settings.invalid_link'));
            }
        };
    }

    /** @param array<int, mixed> $short @param array<int, mixed> $long @return array<string, array<int, mixed>> */
    private function storeCopyRules(string $locale, array $short, array $long): array
    {
        return [
            "store_heading_{$locale}" => $short,
            "store_lead_{$locale}" => $long,
            "store_benefit_1_title_{$locale}" => $short,
            "store_benefit_1_text_{$locale}" => $long,
            "store_benefit_2_title_{$locale}" => $short,
            "store_benefit_2_text_{$locale}" => $long,
            "store_benefit_3_title_{$locale}" => $short,
            "store_benefit_3_text_{$locale}" => $long,
        ];
    }

    /** @param array<int, mixed> $short @param array<int, mixed> $long @return array<string, array<int, mixed>> */
    private function homeContentRules(string $locale, array $short, array $long): array
    {
        $rules = [];
        foreach (['chooser_kicker', 'chooser_title', 'chooser_mobile_title', 'chooser_workshop_title', 'chooser_store_kicker', 'chooser_store_title', 'trust_1', 'trust_2', 'trust_3', 'process_kicker', 'process_title', 'process_1_title', 'process_2_title', 'process_3_title', 'process_4_title', 'story_kicker', 'story_title', 'business_kicker', 'business_title', 'business_proof', 'logistics_kicker', 'logistics_title', 'hotel_kicker', 'hotel_title', 'hotel_cta', 'mobile_kicker', 'mobile_title', 'mobile_cta', 'contact_kicker', 'contact_title'] as $field) {
            $rules["{$field}_{$locale}"] = $short;
        }
        foreach (['chooser_mobile_text', 'chooser_workshop_text', 'process_lead', 'process_1_text', 'process_2_text', 'process_3_text', 'process_4_text', 'story_text', 'story_quote', 'business_text', 'hotel_text', 'mobile_text', 'contact_text'] as $field) {
            $rules["{$field}_{$locale}"] = $long;
        }

        return $rules;
    }
}
