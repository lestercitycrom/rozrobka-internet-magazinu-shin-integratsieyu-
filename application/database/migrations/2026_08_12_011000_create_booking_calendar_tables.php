<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_schedule_intervals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('weekday');
            $table->string('location', 16);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['location', 'weekday', 'is_active'], 'booking_intervals_lookup');
        });

        Schema::create('booking_blackouts', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at'], 'booking_blackouts_lookup');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->unique()->after('reference');
            $table->foreignId('booking_schedule_interval_id')->nullable()->after('preferred_window')
                ->constrained('booking_schedule_intervals')->nullOnDelete();
            $table->string('booking_slot_key', 80)->nullable()->after('booking_schedule_interval_id');
            $table->dateTime('booking_starts_at')->nullable()->after('booking_slot_key');
            $table->dateTime('booking_ends_at')->nullable()->after('booking_starts_at');
            $table->index(['booking_slot_key', 'status'], 'service_requests_slot_capacity');
        });

        Schema::create('service_request_submissions', function (Blueprint $table): void {
            $table->uuid('token')->primary();
            $table->foreignId('service_request_id')->nullable()->unique()->constrained('service_requests')->cascadeOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_submissions');

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropIndex('service_requests_slot_capacity');
            $table->dropConstrainedForeignId('booking_schedule_interval_id');
            $table->dropUnique(['submission_token']);
            $table->dropColumn(['submission_token', 'booking_slot_key', 'booking_starts_at', 'booking_ends_at']);
        });

        Schema::dropIfExists('booking_blackouts');
        Schema::dropIfExists('booking_schedule_intervals');
    }
};
