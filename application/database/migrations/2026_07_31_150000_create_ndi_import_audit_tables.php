<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('import_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('run_type', 64);
            $table->string('status', 64);
            $table->string('trigger', 64);
            $table->string('parser_version', 64);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });

        Schema::create('source_artifacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('kind', 64);
            $table->string('original_filename');
            $table->string('storage_key')->unique();
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->timestamp('received_at');
            $table->string('content_type', 255)->nullable();
            $table->boolean('is_redacted')->default(false);
            $table->unique(['supplier_id', 'sha256']);
            $table->index(['supplier_id', 'received_at']);
        });

        Schema::create('import_run_artifacts', function (Blueprint $table): void {
            $table->foreignUlid('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->string('dataset_role', 64);
            $table->string('parser_version', 64);
            $table->unique(
                ['import_run_id', 'source_artifact_id', 'dataset_role'],
                'import_run_artifacts_run_artifact_role_unique',
            );
        });

        Schema::create('import_rejections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('external_sku', 255)->nullable();
            $table->string('field_name', 255)->nullable();
            $table->string('reason');
            $table->json('raw_row_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rejections');
        Schema::dropIfExists('import_run_artifacts');
        Schema::dropIfExists('source_artifacts');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('suppliers');
    }
};
