<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->string('assigned_van', 16)->nullable()->after('fulfilment');
            $table->index(['fulfilment', 'assigned_van'], 'service_requests_fulfilment_van_index');
        });
    }

    public function down(): void
    {
        // Additive CRM planning data is deliberately retained during code rollback.
    }
};
