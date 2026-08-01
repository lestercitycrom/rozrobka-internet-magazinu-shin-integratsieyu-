<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addons', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 64)->unique();
            $table->string('title_nb');
            $table->string('title_ru');
            $table->string('applicability', 16);
            $table->string('billing_mode', 16);
            $table->char('currency', 3)->default('NOK');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
            $table->index(['applicability', 'billing_mode']);
        });

        Schema::create('order_service_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_addon_id')->nullable()->constrained('service_addons')->nullOnDelete();
            $table->string('service_code', 64);
            $table->string('title_nb');
            $table->string('title_ru');
            $table->string('applicability', 16);
            $table->string('billing_mode', 16);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->json('service_snapshot');
            $table->timestamps();
            $table->unique(['order_id', 'service_code']);
        });

        $now = now();
        DB::table('service_addons')->insert([
            [
                'code' => 'mounting',
                'title_nb' => 'Montering',
                'title_ru' => 'Монтаж',
                'applicability' => 'tire',
                'billing_mode' => 'per_item',
                'currency' => 'NOK',
                'price_minor' => null,
                'is_active' => false,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'balancing',
                'title_nb' => 'Balansering',
                'title_ru' => 'Балансировка',
                'applicability' => 'all',
                'billing_mode' => 'per_item',
                'currency' => 'NOK',
                'price_minor' => null,
                'is_active' => false,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_service_items');
        Schema::dropIfExists('service_addons');
    }
};
