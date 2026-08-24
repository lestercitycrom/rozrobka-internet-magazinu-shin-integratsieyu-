<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('terms_version', 32)->default('legacy-2026-08-11')->after('terms_accepted_at');
            $table->string('privacy_version', 32)->default('legacy-2026-08-11')->after('privacy_accepted_at');
            $table->boolean('fitment_confirmed')->default(false)->after('fitment_snapshot');
            $table->boolean('payment_obligation_confirmed')->default(false)->after('fitment_confirmed');
            $table->boolean('early_service_start_requested')->default(false)->after('payment_obligation_confirmed');
            $table->timestamp('early_service_start_requested_at')->nullable()->after('early_service_start_requested');
            $table->json('legal_acceptance_snapshot')->nullable()->after('early_service_start_requested_at');
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('payment_product_type', 96)->nullable()->after('currency');
            $table->timestamp('authorization_expires_at')->nullable()->after('authorized_at');
            $table->unsignedBigInteger('captured_amount_minor')->default(0)->after('authorization_expires_at');
            $table->unsignedBigInteger('refunded_amount_minor')->default(0)->after('captured_amount_minor');
            $table->string('card_last_four', 4)->nullable()->after('refunded_amount_minor');
            $table->uuid('refund_idempotency_key')->nullable()->unique()->after('void_idempotency_key');
            $table->timestamp('refunded_at')->nullable()->after('voided_at');
        });

        Schema::create('payment_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('payment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 32);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3);
            $table->longText('items_encrypted')->nullable();
            $table->text('note')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('error_code', 96)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'started_at']);
            $table->index(['payment_attempt_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_operations');

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique(['refund_idempotency_key']);
            $table->dropColumn([
                'payment_product_type',
                'authorization_expires_at',
                'captured_amount_minor',
                'refunded_amount_minor',
                'card_last_four',
                'refund_idempotency_key',
                'refunded_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'terms_version',
                'privacy_version',
                'fitment_confirmed',
                'payment_obligation_confirmed',
                'early_service_start_requested',
                'early_service_start_requested_at',
                'legal_acceptance_snapshot',
            ]);
        });
    }
};
