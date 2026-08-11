<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_pages', function (Blueprint $table): void {
            $table->string('canonical_path', 160)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('service_pages', function (Blueprint $table): void {
            $table->dropUnique(['canonical_path']);
            $table->dropColumn('canonical_path');
        });
    }
};
