<?php

namespace Database\Seeders;

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepageBusinessPartner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Guarded rollback for Issue #47; operator-owned rows and media are retained. */
final class RollbackCustomerHomepageCompositionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            HomepageBusinessPartner::query()
                ->where('content_key', 'like', 'issue-47-%')
                ->whereNotNull('manifest_hash')
                ->update(['publication_status' => ContentPageStatus::Draft->value]);

            PublicServicePrice::query()
                ->where('code', 'hotel-r15')
                ->where('currency', 'NOK')
                ->where('price_minor', 70000)
                ->where('manifest_hash', CustomerHomepageCompositionSeeder::HOTEL_PRICE_MANIFEST)
                ->update(['price_minor' => 75000, 'manifest_hash' => null]);
        });
    }
}
