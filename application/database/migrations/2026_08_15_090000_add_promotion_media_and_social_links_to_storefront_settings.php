<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_promotions', function (Blueprint $table): void {
            $table->foreignUlid('media_asset_id')
                ->nullable()
                ->after('cta_url')
                ->constrained('media_assets')
                ->nullOnDelete();
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->string('instagram_url', 2048)->nullable()->after('map_embed_url');
            $table->string('snapchat_url', 2048)->nullable()->after('instagram_url');
            $table->string('facebook_url', 2048)->nullable()->after('snapchat_url');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_promotions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_asset_id');
        });

        Schema::table('storefront_settings', function (Blueprint $table): void {
            $table->dropColumn(['instagram_url', 'snapchat_url', 'facebook_url']);
        });
    }
};
