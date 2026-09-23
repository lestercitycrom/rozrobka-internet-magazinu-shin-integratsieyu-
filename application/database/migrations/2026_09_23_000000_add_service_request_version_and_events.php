<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_requests', 'operation_version')) {
            Schema::table('service_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('operation_version')->default(1)->after('status');
            });
        }

        if (! Schema::hasTable('service_request_events')) {
            Schema::create('service_request_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_request_id')->constrained('service_requests')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->uuid('command_id')->unique();
                $table->string('command', 64);
                $table->json('changes');
                $table->timestamp('created_at');

                $table->index(['service_request_id', 'created_at'], 'service_request_events_timeline');
            });
        }
    }

    public function down(): void
    {
        // Deliberately additive. A code rollback may ignore the version and event
        // data, but must not destroy the operational history created after release.
    }
};
