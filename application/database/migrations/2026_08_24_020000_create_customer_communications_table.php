<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_communications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('recipient');
            $table->string('locale', 8);
            $table->string('document_version', 32);
            $table->timestamp('prepared_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communications');
    }
};
