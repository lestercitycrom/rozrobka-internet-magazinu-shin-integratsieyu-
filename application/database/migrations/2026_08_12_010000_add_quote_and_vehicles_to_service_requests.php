<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->json('vehicles')->nullable()->after('additional_services');
            $table->unsignedInteger('quote_min_minor')->nullable()->after('vehicles');
            $table->unsignedInteger('quote_max_minor')->nullable()->after('quote_min_minor');
            $table->char('quote_currency', 3)->nullable()->after('quote_max_minor');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['vehicles', 'quote_min_minor', 'quote_max_minor', 'quote_currency']);
        });
    }
};
