<?php

namespace Database\Seeders;

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageBusinessPartner;
use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Guarded rollback for Issue #47; operator-owned rows and media are retained. */
final class RollbackCustomerHomepageCompositionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->rollbackSettings();

            HomepageBusinessPartner::query()
                ->where('content_key', 'like', 'issue-47-%')
                ->whereNotNull('manifest_hash')
                ->update(['publication_status' => ContentPageStatus::Draft->value]);

            $this->rollbackHotelPrice();
        });
    }

    private function rollbackSettings(): void
    {
        $settings = StorefrontSettings::query()->find(1);
        $manifest = $settings?->customer_homepage_composition_manifest;
        if ($settings === null || ! is_array($manifest) || ($manifest['release'] ?? null) !== CustomerHomepageCompositionSeeder::RELEASE || ($manifest['state'] ?? null) !== 'applied') {
            return;
        }

        foreach ($manifest['changes'] ?? [] as $field => $change) {
            if (is_array($change) && ! ($change['operator_owned'] ?? false) && $settings->getAttribute($field) === ($change['applied'] ?? null)) {
                $settings->setAttribute($field, $change['original'] ?? null);
            }
        }

        $manifest['state'] = 'rolled_back';
        $settings->customer_homepage_composition_manifest = $manifest;
        $settings->save();
    }

    private function rollbackHotelPrice(): void
    {
        $price = PublicServicePrice::query()->where('code', 'hotel-r15')->where('currency', 'NOK')->first();
        $manifest = $price?->customer_homepage_composition_manifest;
        if ($price === null || ! is_array($manifest) || ($manifest['release'] ?? null) !== CustomerHomepageCompositionSeeder::RELEASE || ($manifest['state'] ?? null) !== 'applied') {
            return;
        }

        $change = $manifest['changes']['price_minor'] ?? null;
        if (is_array($change) && ! ($change['operator_owned'] ?? false) && $price->price_minor === ($change['applied'] ?? null)) {
            $price->price_minor = $change['original'];
        }
        if ($price->manifest_hash === CustomerHomepageCompositionSeeder::HOTEL_PRICE_MANIFEST) {
            $price->manifest_hash = null;
        }

        $manifest['state'] = 'rolled_back';
        $price->customer_homepage_composition_manifest = $manifest;
        $price->save();
    }
}
