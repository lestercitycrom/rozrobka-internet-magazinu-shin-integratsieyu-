<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ndi_inbox_receipts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('fingerprint', 64)->unique();
            $table->char('file_key_hash', 64);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedBigInteger('file_modified_at');
            $table->unsignedBigInteger('file_changed_at');
            $table->unsignedBigInteger('file_inode');
            $table->string('status', 32);
            $table->foreignUlid('import_run_id')->nullable()->constrained('import_runs')->nullOnDelete();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ndi_inbox_receipts');
    }
};
