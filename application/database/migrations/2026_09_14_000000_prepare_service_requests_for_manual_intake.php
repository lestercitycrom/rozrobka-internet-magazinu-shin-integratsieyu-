<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->timestamp('consent_at')->nullable()->change();
            $table->string('source', 32)->default('public')->after('locale');
            $table->foreignId('created_by_user_id')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
            $table->string('phone_normalized', 32)->nullable()->after('phone');
            $table->index('name', 'service_requests_name_lookup');
            $table->index('email', 'service_requests_email_lookup');
            $table->index('phone_normalized', 'service_requests_phone_lookup');
            $table->index(['preferred_date', 'service_slug', 'fulfilment', 'status'], 'service_requests_operational_filters');
            $table->index(['source', 'created_at'], 'service_requests_source_created');
        });

        Schema::table('service_request_submissions', function (Blueprint $table): void {
            $table->string('source', 32)->default('public')->after('token');
            $table->foreignId('actor_user_id')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
            $table->char('session_fingerprint', 64)->nullable()->after('actor_user_id');
            $table->index(['source', 'actor_user_id'], 'service_request_submissions_owner');
        });

        DB::table('service_requests')->select(['id', 'phone'])->orderBy('id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $compact = preg_replace('/[\s().-]+/u', '', trim((string) $row->phone));
                if (! is_string($compact) || $compact === '') {
                    continue;
                }
                if (str_starts_with($compact, '00')) {
                    $compact = '+'.substr($compact, 2);
                }
                if (! str_starts_with($compact, '+')) {
                    $compact = '+47'.$compact;
                }
                $normalized = preg_replace('/\D+/', '', $compact);
                if (is_string($normalized) && $normalized !== '') {
                    DB::table('service_requests')->where('id', $row->id)->update(['phone_normalized' => $normalized]);
                }
            }
        });
    }

    public function down(): void
    {
        // Deliberately additive. Narrowing nullable contact/consent fields or
        // dropping manual-request provenance could destroy accepted records.
        // Rollback uses compatible application code while retaining this data.
    }
};
