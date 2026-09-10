<?php

namespace Database\Seeders;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageServiceCard;
use Illuminate\Database\Seeder;

/**
 * Guarded Issue #46 content rollback.
 *
 * Rows still carrying their manifest hash have not been edited through the
 * admin. They are unpublished, never deleted. Operator-edited and manually
 * created rows, plus all media, are retained.
 */
final class RollbackCustomerHomepageCollectionsSeeder extends Seeder
{
    public function run(): void
    {
        HomepagePromotion::query()
            ->where('content_key', 'like', 'issue-46-offer-%')
            ->whereNotNull('manifest_hash')
            ->update(['publication_status' => ContentPageStatus::Draft->value]);

        HomepageServiceCard::query()
            ->where('content_key', 'like', 'issue-46-%')
            ->whereNotNull('manifest_hash')
            ->update(['publication_status' => ContentPageStatus::Draft->value]);
    }
}
