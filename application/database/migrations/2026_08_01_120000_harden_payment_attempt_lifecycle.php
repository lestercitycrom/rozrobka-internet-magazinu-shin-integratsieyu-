<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->uuid('capture_idempotency_key')->nullable()->unique();
            $table->uuid('void_idempotency_key')->nullable()->unique();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
            $table->dropUnique(['capture_idempotency_key']);
            $table->dropUnique(['void_idempotency_key']);
            $table->dropColumn([
                'capture_idempotency_key',
                'void_idempotency_key',
                'authorized_at',
                'captured_at',
                'voided_at',
            ]);
        });
    }
};
