<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ndi_availability_checks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->string('document_id', 50)->unique();
            $table->string('source_action', 32);
            $table->char('request_fingerprint', 64);
            $table->string('status', 32);
            $table->string('error_code', 64)->nullable();
            $table->text('response_summary_encrypted')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'request_fingerprint', 'status'], 'ndi_availability_order_fingerprint_status');
            $table->index(['status', 'expires_at']);
        });

        Schema::create('ndi_order_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('customer_reference', 50)->unique();
            $table->char('request_fingerprint', 64);
            $table->string('status', 32);
            $table->string('external_order_reference', 50)->nullable()->index();
            $table->string('error_code', 64)->nullable();
            $table->text('response_summary_encrypted')->nullable();
            $table->string('sales_status', 100)->nullable();
            $table->string('document_status', 100)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->text('tracking_summary_encrypted')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamp('despatch_checked_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ndi_order_attempts');
        Schema::dropIfExists('ndi_availability_checks');
    }
};
