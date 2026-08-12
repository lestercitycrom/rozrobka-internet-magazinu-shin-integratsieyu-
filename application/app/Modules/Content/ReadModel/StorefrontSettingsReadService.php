<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Support\Facades\Schema;

final class StorefrontSettingsReadService
{
    public function current(): ?StorefrontSettings
    {
        if (! Schema::hasTable('storefront_settings')) {
            return null;
        }

        try {
            return StorefrontSettings::query()->with(['heroMedia', 'homeStoreMedia', 'storyMedia', 'hotelMedia', 'mobileMedia'])->find(1);
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
}
