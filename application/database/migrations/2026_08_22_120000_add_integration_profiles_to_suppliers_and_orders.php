<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('integration_profile', 32)->default('ndi_live')->after('name');
            $table->boolean('is_test_fixture')->default(false)->after('integration_profile');
            $table->index(['integration_profile', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('integration_profile', 32)->default('ndi_live')->after('ndi_status');
            $table->boolean('is_integration_test')->default(false)->after('integration_profile');
            $table->index(['is_integration_test', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['is_integration_test', 'submitted_at']);
            $table->dropColumn(['integration_profile', 'is_integration_test']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex(['integration_profile', 'is_active']);
            $table->dropColumn(['integration_profile', 'is_test_fixture']);
        });
    }
};
