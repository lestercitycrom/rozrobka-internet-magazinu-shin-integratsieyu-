<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateStorefrontSettingsRequest;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class StorefrontSettingsController
{
    public function edit(): View
    {
        return view('admin.storefront-settings.edit', [
            'settings' => StorefrontSettings::query()->with(['heroMedia', 'homeStoreMedia', 'storyMedia', 'hotelMedia', 'mobileMedia', 'hotelVideoMedia'])->find(1) ?? new StorefrontSettings,
            'assets' => MediaAsset::query()->where('is_active', true)->whereIn('mime', ['image/jpeg', 'image/png', 'image/webp'])->orderByDesc('created_at')->get(),
            'videoAssets' => MediaAsset::query()->where('is_active', true)->whereIn('mime', ['video/mp4', 'video/webm'])->orderByDesc('created_at')->get(),
            'defaults' => $this->defaults(),
        ]);
    }

    public function update(UpdateStorefrontSettingsRequest $request): RedirectResponse
    {
        $settings = StorefrontSettings::query()->find(1) ?? new StorefrontSettings(['id' => 1]);
        $validated = $request->validated();
        $manifest = $settings->customer_homepage_composition_manifest;

        if (is_array($manifest) && ($manifest['state'] ?? null) === 'applied') {
            $changes = $manifest['changes'] ?? [];
            foreach ($changes as $field => &$change) {
                if (is_array($change) && array_key_exists($field, $validated) && $settings->getAttribute($field) !== $validated[$field]) {
                    $change['operator_owned'] = true;
                }
            }
            unset($change);
            $manifest['changes'] = $changes;
        }

        $settings->fill($validated);
        if (is_array($manifest)) {
            $settings->customer_homepage_composition_manifest = $manifest;
        }
        $settings->save();

        return redirect()->route('admin.storefront-settings.edit')->with('status', __('storefront_settings.saved'));
    }

    /** @return array<string, string> */
    private function defaults(): array
    {
        return [
            'hero_kicker_nb' => __('pro.home.hero_kicker'), 'hero_title_nb' => __('pro.home.hero_title'), 'hero_text_nb' => __('pro.home.hero_text'),
            'hero_primary_label_nb' => __('pro.home.hero_primary'), 'hero_secondary_label_nb' => __('pro.home.hero_secondary'),
            'hero_kicker_ru' => __('pro.home.hero_kicker', [], 'ru'), 'hero_title_ru' => __('pro.home.hero_title', [], 'ru'), 'hero_text_ru' => __('pro.home.hero_text', [], 'ru'),
            'hero_primary_label_ru' => __('pro.home.hero_primary', [], 'ru'), 'hero_secondary_label_ru' => __('pro.home.hero_secondary', [], 'ru'),
            'phone_display' => (string) config('storefront.phone_display'), 'phone_href' => (string) config('storefront.phone_href'), 'email' => (string) config('storefront.email'),
            'business_address' => (string) config('storefront.business_address'), 'map_pin_address' => (string) config('storefront.map_pin_address'),
            'map_url' => (string) config('storefront.map_url'), 'map_embed_url' => (string) config('storefront.map_embed_url'),
            'store_heading_nb' => __('seo_store.heading'), 'store_lead_nb' => __('seo_store.lead'),
            'store_heading_ru' => __('seo_store.heading', [], 'ru'), 'store_lead_ru' => __('seo_store.lead', [], 'ru'),
            ...$this->benefitDefaults('nb'), ...$this->benefitDefaults('ru'),
            ...$this->homeDefaults('nb'), ...$this->homeDefaults('ru'),
        ];
    }

    /** @return array<string, string> */
    private function benefitDefaults(string $locale): array
    {
        $defaults = [];
        foreach ([1, 2, 3] as $step) {
            $defaults["store_benefit_{$step}_title_{$locale}"] = __('seo_store.advantage_'.$step.'_title', [], $locale);
            $defaults["store_benefit_{$step}_text_{$locale}"] = __('seo_store.advantage_'.$step.'_text', [], $locale);
        }

        return $defaults;
    }

    /** @return array<string, string> */
    private function homeDefaults(string $locale): array
    {
        $pro = static fn (string $key): string => __('pro.home.'.$key, [], $locale);
        $project = static fn (string $key): string => __('project.home.'.$key, [], $locale);

        return [
            "chooser_kicker_{$locale}" => $pro('start_here'), "chooser_title_{$locale}" => $pro('location_title'),
            "chooser_mobile_title_{$locale}" => $pro('location_mobile'), "chooser_mobile_text_{$locale}" => $pro('location_mobile_text'),
            "chooser_workshop_title_{$locale}" => $pro('location_workshop'), "chooser_workshop_text_{$locale}" => $pro('location_workshop_text'),
            "chooser_store_kicker_{$locale}" => $pro('need_wheels'), "chooser_store_title_{$locale}" => $pro('search_registration'),
            "trust_1_{$locale}" => $pro('choice_fast_title'), "trust_2_{$locale}" => $pro('choice_store_title'), "trust_3_{$locale}" => $pro('choice_help_title'),
            "process_kicker_{$locale}" => $pro('process_kicker'), "process_title_{$locale}" => $project('process_title'), "process_lead_{$locale}" => $project('process_note'),
            "process_1_title_{$locale}" => $project('process_1_title'), "process_1_text_{$locale}" => $project('process_1_text'),
            "process_2_title_{$locale}" => $project('process_2_title'), "process_2_text_{$locale}" => $project('process_2_text'),
            "process_3_title_{$locale}" => $project('process_3_title'), "process_3_text_{$locale}" => $project('process_3_text'),
            "process_4_title_{$locale}" => $project('process_4_title'), "process_4_text_{$locale}" => $project('process_4_text'),
            "process_cta_{$locale}" => $locale === 'ru' ? 'Заказать сейчас!' : 'Bestill nå!',
            "story_kicker_{$locale}" => $pro('story_kicker'), "story_title_{$locale}" => $pro('story_title'), "story_text_{$locale}" => $pro('story_text'), "story_quote_{$locale}" => $pro('story_quote'),
            "business_kicker_{$locale}" => $pro('business_kicker'), "business_title_{$locale}" => $project('business_title'), "business_text_{$locale}" => $project('business_text'), "business_proof_{$locale}" => $project('business_proof'),
            "business_cta_{$locale}" => $locale === 'ru' ? 'Запросить предложение' : 'Be om et tilbud', "business_partners_heading_{$locale}" => $locale === 'ru' ? 'Некоторые компании, которым мы помогаем' : 'Noen av bedriftene vi hjelper',
            "logistics_kicker_{$locale}" => $pro('hero_proof_hotel').' · '.$pro('hero_proof_mobile'), "logistics_title_{$locale}" => $pro('location_title'),
            "hotel_kicker_{$locale}" => $pro('hotel_kicker'), "hotel_title_{$locale}" => $pro('hotel_title'), "hotel_text_{$locale}" => $pro('hotel_text'), "hotel_cta_{$locale}" => $pro('hotel_link'),
            "mobile_kicker_{$locale}" => $pro('mobile_kicker'), "mobile_title_{$locale}" => $pro('mobile_title'), "mobile_text_{$locale}" => $pro('mobile_text'), "mobile_cta_{$locale}" => $pro('mobile_link'),
            "mobile_details_title_{$locale}" => $locale === 'ru' ? 'Шинный отель без хлопот' : 'Dekkhotell uten stress',
            "mobile_details_body_1_{$locale}" => $locale === 'ru' ? 'С шинным отелем EasyTire вам не придётся самостоятельно поднимать, перевозить или хранить колёса. Когда наступит время сезонной замены, мы привезём нужный комплект к вашему автомобилю, заменим колёса на месте и заберём второй комплект обратно в шинный отель.' : 'Med dekkhotell hos EasyTire slipper du å løfte, frakte eller oppbevare hjulene selv. Når det er tid for sesongskifte, tar vi med riktig hjulsett til bilen din, skifter hjulene på stedet og tar med det andre settet tilbake til dekkhotellet.',
            "mobile_details_body_2_{$locale}" => $locale === 'ru' ? 'Если шины изношены, мы можем заказать и установить новые, пока колёса находятся у нас. К следующей сезонной замене они будут готовы.' : 'Er dekkene utslitt, kan vi skaffe og montere nye dekk mens hjulene står hos oss. De er da klare til neste dekkskift.',
            "contact_kicker_{$locale}" => $pro('contact_kicker'), "contact_title_{$locale}" => $pro('contact_title'), "contact_text_{$locale}" => $pro('contact_text'),
            "contact_map_note_{$locale}" => $locale === 'ru' ? 'Google Maps показывает правильный въезд через Ringvegen 202.' : 'Google Maps viser riktig innkjøring via Ringvegen 202.',
        ];
    }
}
