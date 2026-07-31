<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('scope', 32);
            $table->string('scope_key')->nullable();
            $table->string('source_price_list_code', 64);
            $table->char('currency', 3)->default('NOK');
            $table->integer('markup_basis_points')->default(0);
            $table->integer('fixed_markup_minor')->default(0);
            $table->unsignedInteger('rounding_increment_minor')->default(1);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'priority']);
            $table->index(['scope', 'scope_key']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('public_reference', 32)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('locale', 8)->default('nb');
            $table->string('status', 32);
            $table->string('payment_status', 32);
            $table->string('ndi_status', 32);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('fulfilment_method', 32);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 64);
            $table->string('vehicle_registration', 32)->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('privacy_accepted_at');
            $table->timestamp('pricing_snapshot_at');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
            $table->index(['customer_email', 'submitted_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_sku_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_sku');
            $table->string('product_title');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->json('product_snapshot');
            $table->json('pricing_snapshot');
            $table->timestamp('source_price_observed_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'external_sku']);
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('pricing_rules');
    }
};
