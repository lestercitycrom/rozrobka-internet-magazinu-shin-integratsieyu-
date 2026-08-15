<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('homepage_promotions', 'media_asset_id')) {
            Schema::table('homepage_promotions', function (Blueprint $table): void {
                $table->foreignUlid('media_asset_id')
                    ->nullable()
                    ->after('cta_url')
                    ->constrained('media_assets')
                    ->nullOnDelete();
            });
        }

        foreach ([
            'instagram_url' => 'map_embed_url',
            'snapchat_url' => 'instagram_url',
            'facebook_url' => 'snapchat_url',
        ] as $column => $after) {
            if (Schema::hasColumn('storefront_settings', $column)) {
                continue;
            }

            Schema::table('storefront_settings', function (Blueprint $table) use ($column, $after): void {
                // This settings table already contains extensive localized copy.
                // TEXT keeps public URLs out of InnoDB's 65 KB inline-row limit.
                $table->text($column)->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('homepage_promotions', 'media_asset_id')) {
            Schema::table('homepage_promotions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('media_asset_id');
            });
        }

        foreach (['facebook_url', 'snapchat_url', 'instagram_url'] as $column) {
            if (! Schema::hasColumn('storefront_settings', $column)) {
                continue;
            }

            Schema::table('storefront_settings', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
