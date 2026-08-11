<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->string('service_variant', 64)->nullable()->after('service_slug');
            $table->unsignedTinyInteger('wheel_count')->nullable()->after('tire_size');
            $table->json('additional_services')->nullable()->after('dekkhotell');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropColumn(['service_variant', 'wheel_count', 'additional_services']);
        });
    }
};
