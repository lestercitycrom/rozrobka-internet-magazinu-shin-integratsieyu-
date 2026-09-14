<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 24);
            $table->string('phone_normalized', 32);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('district', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name', 'clients_name_lookup');
            $table->index('email', 'clients_email_lookup');
            $table->index('phone_normalized', 'clients_phone_lookup');
            $table->index('district', 'clients_district_lookup');
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->after('created_by_user_id')
                ->constrained('clients')->nullOnDelete();
            $table->timestamp('preferred_period_starts_at')->nullable()->after('preferred_window');
            $table->timestamp('preferred_period_ends_at')->nullable()->after('preferred_period_starts_at');
            $table->string('district', 120)->nullable()->after('address');

            $table->index(['district', 'dekkhotell'], 'service_requests_crm_filters');
        });
    }

    public function down(): void
    {
        // Deliberately additive: a code rollback must not destroy client records,
        // links or the structured time preferences collected after this release.
    }
};
