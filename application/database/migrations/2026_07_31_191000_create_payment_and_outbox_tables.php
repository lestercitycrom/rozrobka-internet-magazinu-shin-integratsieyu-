<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64);
            $table->uuid('idempotency_key')->unique();
            $table->string('provider_reference')->nullable();
            $table->string('status', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->text('metadata_encrypted')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('provider', 64);
            $table->string('external_event_id');
            $table->foreignUlid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payload_sha256', 64);
            $table->string('outcome', 32);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_event_id']);
        });

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 96);
            $table->string('aggregate_type', 64);
            $table->string('aggregate_id', 64);
            $table->string('idempotency_key')->unique();
            $table->longText('payload_encrypted');
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error_code', 96)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_attempts');
    }
};
