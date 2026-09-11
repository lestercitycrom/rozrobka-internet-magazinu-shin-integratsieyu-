<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Content\Models\StorefrontSettings;
use App\Support\PublicHttpUrl;
use Illuminate\Support\Facades\Schema;

final class StorefrontSettingsReadService
{
    public function current(): ?StorefrontSettings
    {
        if (! Schema::hasTable('storefront_settings')) {
            return null;
        }

        try {
            return StorefrontSettings::query()->with(['heroMedia', 'homeStoreMedia', 'storyMedia', 'hotelMedia', 'mobileMedia', 'hotelVideoMedia'])->find(1);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{phone_display: string, phone_href: string, email: string} */
    public function contact(): array
    {
        $settings = $this->current();
        $value = static function (string $field, string $fallback) use ($settings): string {
            $candidate = trim((string) $settings?->getAttribute($field));

            return $candidate !== '' ? $candidate : $fallback;
        };

        return [
            'phone_display' => $value('phone_display', (string) config('storefront.phone_display')),
            'phone_href' => $value('phone_href', (string) config('storefront.phone_href')),
            'email' => $value('email', (string) config('storefront.email')),
        ];
    }

    /** @return array{instagram_url: ?string, snapchat_url: ?string, facebook_url: ?string} */
    public function socialLinks(): array
    {
        $settings = $this->current();

        return [
            'instagram_url' => PublicHttpUrl::sanitize($settings?->instagram_url)
                ?? PublicHttpUrl::sanitize(config('storefront.instagram_url')),
            'snapchat_url' => PublicHttpUrl::sanitize($settings?->snapchat_url),
            'facebook_url' => PublicHttpUrl::sanitize($settings?->facebook_url)
                ?? PublicHttpUrl::sanitize(config('storefront.facebook_url')),
        ];
    }
}
