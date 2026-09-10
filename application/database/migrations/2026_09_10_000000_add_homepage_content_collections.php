<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_promotions', function (Blueprint $table): void {
            $table->string('content_key', 120)->nullable()->unique()->after('id');
            $table->char('manifest_hash', 64)->nullable()->after('content_key');
            $table->text('short_body_nb')->nullable()->after('title_nb');
            $table->text('short_body_ru')->nullable()->after('title_ru');
            $table->softDeletes();
        });

        Schema::create('homepage_service_cards', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('content_key', 120)->nullable()->unique();
            $table->char('manifest_hash', 64)->nullable();
            $table->string('collection', 32);
            $table->foreignId('service_page_id')->nullable()->constrained('service_pages')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('publication_status', 16)->default('draft');
            foreach (['nb', 'ru'] as $locale) {
                $table->string("title_{$locale}");
                $table->text("description_{$locale}")->nullable();
                $table->string("cta_label_{$locale}")->nullable();
            }
            $table->string('cta_url', 2048)->nullable();
            $table->foreignUlid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['collection', 'publication_status', 'sort_order'], 'homepage_service_cards_public_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_service_cards');

        Schema::table('homepage_promotions', function (Blueprint $table): void {
            $table->dropUnique(['content_key']);
            $table->dropColumn(['content_key', 'manifest_hash', 'short_body_nb', 'short_body_ru', 'deleted_at']);
        });
    }
};
